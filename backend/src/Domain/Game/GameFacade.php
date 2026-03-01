<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Ai\Operation\PlayerRelationshipInput;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseGameIsNotInProgressException;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseSimulationFailedException;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseUserIsNotPlayerException;
use App\Domain\Game\Result\CreateGameResult;
use App\Domain\Game\Result\GameEventsResult;
use App\Domain\Game\Result\PreviewTickResult;
use App\Domain\Game\Result\ProcessTickResult;
use App\Domain\Game\Result\StartGameResult;
use App\Domain\Player\Player;
use App\Domain\Player\PlayerRepository;
use App\Domain\Player\PlayerService;
use App\Domain\Relationship\RelationshipRepository;
use App\Domain\Relationship\RelationshipService;
use App\Domain\TraitDef\TraitDefRepository;
use App\Domain\User\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class GameFacade
{
    private readonly EntityManagerInterface $entityManager;

    private readonly GameService $gameService;

    private readonly GameRepository $gameRepository;

    private readonly GameEventRepository $gameEventRepository;

    private readonly PlayerService $playerService;

    private readonly PlayerRepository $playerRepository;

    private readonly RelationshipService $relationshipService;

    private readonly TraitDefRepository $traitDefRepository;

    private readonly SimulationAiService $simulationAiService;

    private readonly SimulationService $simulationService;

    private readonly RelationshipRepository $relationshipRepository;

    private readonly MajorEventRepository $majorEventRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        GameService $gameService,
        GameRepository $gameRepository,
        GameEventRepository $gameEventRepository,
        PlayerService $playerService,
        PlayerRepository $playerRepository,
        RelationshipService $relationshipService,
        TraitDefRepository $traitDefRepository,
        SimulationAiService $simulationAiService,
        SimulationService $simulationService,
        RelationshipRepository $relationshipRepository,
        MajorEventRepository $majorEventRepository,
    ) {
        $this->entityManager = $entityManager;
        $this->gameService = $gameService;
        $this->gameRepository = $gameRepository;
        $this->gameEventRepository = $gameEventRepository;
        $this->playerService = $playerService;
        $this->playerRepository = $playerRepository;
        $this->relationshipService = $relationshipService;
        $this->traitDefRepository = $traitDefRepository;
        $this->simulationAiService = $simulationAiService;
        $this->simulationService = $simulationService;
        $this->relationshipRepository = $relationshipRepository;
        $this->majorEventRepository = $majorEventRepository;
    }

    /**
     * @param array<string, string> $humanTraitStrengths
     */
    public function createGame(
        User $owner,
        string $humanPlayerName,
        string $humanPlayerDescription,
        array $humanTraitStrengths,
    ): CreateGameResult {
        $now = new DateTimeImmutable();
        $traitDefs = array_values($this->traitDefRepository->findAll());

        $aiTraitStrengths = [];
        for ($i = 0; $i < 5; $i++) {
            $aiTraitStrengths[] = $this->playerService->generateRandomTraitStrengths($traitDefs);
        }

        $batchServiceResult = $this->playerService->generateBatchPlayerTraitsSummaryDescriptions($aiTraitStrengths, $now);

        foreach ($batchServiceResult->getLogs() as $log) {
            $this->entityManager->persist($log);
        }

        $this->entityManager->flush();

        if (!$batchServiceResult->isSuccess()) {
            throw $batchServiceResult->getError();
        }

        $aiDescriptions = $batchServiceResult->getResult()->getSummaries();

        $result = $this->gameService->createGame(
            $owner,
            $humanPlayerName,
            $humanPlayerDescription,
            $humanTraitStrengths,
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        $game = $result->game;

        $this->entityManager->persist($game);

        foreach ($game->getPlayers() as $player) {
            $this->entityManager->persist($player);

            foreach ($player->getPlayerTraits() as $playerTrait) {
                $this->entityManager->persist($playerTrait);
            }
        }

        $playerDataList = $this->buildPlayerRelationshipData($game->getPlayers());
        $relationshipServiceResult = $this->playerService->initializeRelationships($playerDataList, $now);

        foreach ($relationshipServiceResult->getLogs() as $log) {
            $this->entityManager->persist($log);
        }

        if (!$relationshipServiceResult->isSuccess()) {
            $this->entityManager->flush();

            throw $relationshipServiceResult->getError();
        }

        $relationships = $this->relationshipService->initializeRelationships(
            $game->getPlayers(),
            $relationshipServiceResult->getResult(),
            $now,
        );

        foreach ($relationships as $relationship) {
            $this->entityManager->persist($relationship);
        }

        $this->entityManager->flush();

        return $result;
    }

    public function startGame(Uuid $gameId, User $currentUser): StartGameResult
    {
        $now = new DateTimeImmutable();
        $game = $this->gameRepository->getGame($gameId);

        $result = $this->gameService->startGame($game, $currentUser, $now);

        foreach ($result->events as $event) {
            $this->entityManager->persist($event);
        }

        $this->entityManager->flush();

        return $result;
    }

    public function processTick(Uuid $gameId, User $currentUser, string $actionText): ProcessTickResult
    {
        $now = new DateTimeImmutable();
        $game = $this->gameRepository->getGame($gameId);
        $humanPlayer = $this->playerRepository->getHumanPlayerByGame($game->getId());

        $humanPlayerUser = $humanPlayer->getUser();

        if ($humanPlayerUser === null || !$humanPlayerUser->getId()->equals($currentUser->getId())) {
            throw new CannotProcessTickBecauseUserIsNotPlayerException($game, $currentUser);
        }

        // 1. Save current day/hour/tick before any mutation
        /** @var int $currentDay */
        $currentDay = $game->getCurrentDay();
        /** @var int $currentHour */
        $currentHour = $game->getCurrentHour();
        /** @var int $currentTick */
        $currentTick = $game->getCurrentTick();

        // 2. Create player action event
        $playerActionEvent = $this->gameService->createPlayerAction($game, $humanPlayer, $actionText, $now);

        // 3. Load context for simulation
        $allPlayers = $game->getPlayers();
        $allRelationships = $this->relationshipRepository->findByGame($game->getId());

        // Last 3 ticks of events for context
        $fromTick = max(0, $currentTick - 3);
        $recentEvents = $this->gameEventRepository->findByGameFromTick($game->getId(), $fromTick);

        // 3b. Load existing memories for context injection + dedup
        $majorEventsByPlayerIndex = [];
        $existingSummaries = [];
        foreach (array_values($allPlayers) as $i => $player) {
            $playerIndex = $i + 1;
            $playerMajorEvents = $this->majorEventRepository->findByGameForPlayer(
                $game->getId(),
                $player->getId(),
                5,
            );
            $majorEventsByPlayerIndex[$playerIndex] = $playerMajorEvents;
            foreach ($playerMajorEvents as $me) {
                $existingSummaries[] = $me->getSummary();
            }
        }
        $existingSummaries = array_values(array_unique($existingSummaries));

        // 4. Build simulation context DTOs
        $playerInputs = SimulationContextBuilder::buildPlayerInputs($allPlayers);
        $relationshipInputs = SimulationContextBuilder::buildRelationshipInputs($allRelationships, $allPlayers);
        $eventInputs = SimulationContextBuilder::buildEventInputs($recentEvents, $allPlayers);
        $humanPlayerIndex = SimulationContextBuilder::findHumanPlayerIndex($allPlayers);
        $memoryInputs = SimulationContextBuilder::buildMemoryInputs($majorEventsByPlayerIndex, $allPlayers);

        // 5. Run AI simulation
        $simulationServiceResult = $this->simulationAiService->simulateTick(
            $currentDay,
            $currentHour,
            $actionText,
            $playerInputs,
            $relationshipInputs,
            $eventInputs,
            $humanPlayerIndex,
            $now,
            $memoryInputs,
        );

        // 6. Always persist AI logs
        foreach ($simulationServiceResult->getLogs() as $log) {
            $this->entityManager->persist($log);
        }

        // 7. If AI failed, flush logs and throw
        if (!$simulationServiceResult->isSuccess()) {
            $this->entityManager->flush();

            throw new CannotProcessTickBecauseSimulationFailedException($game, $simulationServiceResult->getError());
        }

        // 8. Apply simulation results (creates events, adjusts relationships)
        $simulationResult = $this->simulationService->applySimulation(
            $game,
            $simulationServiceResult->getResult(),
            $allPlayers,
            $allRelationships,
            $currentDay,
            $currentHour,
            $currentTick,
            $now,
        );

        // 8b. Extract major events from simulation result
        $majorEventsResult = $this->simulationService->extractMajorEvents(
            $game,
            $simulationResult->events[0],
            $simulationServiceResult->getResult()->getMajorEvents(),
            $allPlayers,
            $existingSummaries,
            $currentDay,
            $currentHour,
            $currentTick,
            $now,
        );

        // 9. Advance game clock (may create NightSleep event)
        $clockEvents = $this->gameService->advanceGameClock($game, $now);

        // 10. Merge all events: player action + simulation + clock
        $allEvents = array_merge([$playerActionEvent], $simulationResult->events, $clockEvents);

        foreach ($allEvents as $event) {
            $this->entityManager->persist($event);
        }

        // 10b. Persist major events
        foreach ($majorEventsResult->majorEvents as $majorEvent) {
            $this->entityManager->persist($majorEvent);
            foreach ($majorEvent->getParticipants() as $participant) {
                $this->entityManager->persist($participant);
            }
        }

        $this->entityManager->flush();

        return new ProcessTickResult($game, $allEvents);
    }

    public function previewTick(Uuid $gameId, User $currentUser, string $actionText): PreviewTickResult
    {
        $now = new DateTimeImmutable();
        $game = $this->gameRepository->getGame($gameId);
        $humanPlayer = $this->playerRepository->getHumanPlayerByGame($game->getId());

        $humanPlayerUser = $humanPlayer->getUser();

        if ($humanPlayerUser === null || !$humanPlayerUser->getId()->equals($currentUser->getId())) {
            throw new CannotProcessTickBecauseUserIsNotPlayerException($game, $currentUser);
        }

        if ($game->getStatus() !== GameStatus::InProgress) {
            throw new CannotProcessTickBecauseGameIsNotInProgressException($game);
        }

        /** @var int $currentDay */
        $currentDay = $game->getCurrentDay();
        /** @var int $currentHour */
        $currentHour = $game->getCurrentHour();
        /** @var int $currentTick */
        $currentTick = $game->getCurrentTick();

        $allPlayers = $game->getPlayers();
        $allRelationships = $this->relationshipRepository->findByGame($game->getId());

        $fromTick = max(0, $currentTick - 3);
        $recentEvents = $this->gameEventRepository->findByGameFromTick($game->getId(), $fromTick);

        // Load existing memories for context injection
        $majorEventsByPlayerIndex = [];
        foreach (array_values($allPlayers) as $i => $player) {
            $playerIndex = $i + 1;
            $majorEventsByPlayerIndex[$playerIndex] = $this->majorEventRepository->findByGameForPlayer(
                $game->getId(),
                $player->getId(),
                5,
            );
        }

        $playerInputs = SimulationContextBuilder::buildPlayerInputs($allPlayers);
        $relationshipInputs = SimulationContextBuilder::buildRelationshipInputs($allRelationships, $allPlayers);
        $eventInputs = SimulationContextBuilder::buildEventInputs($recentEvents, $allPlayers);
        $humanPlayerIndex = SimulationContextBuilder::findHumanPlayerIndex($allPlayers);
        $memoryInputs = SimulationContextBuilder::buildMemoryInputs($majorEventsByPlayerIndex, $allPlayers);

        $simulationServiceResult = $this->simulationAiService->simulateTick(
            $currentDay,
            $currentHour,
            $actionText,
            $playerInputs,
            $relationshipInputs,
            $eventInputs,
            $humanPlayerIndex,
            $now,
            $memoryInputs,
        );

        foreach ($simulationServiceResult->getLogs() as $log) {
            $this->entityManager->persist($log);
        }

        if (!$simulationServiceResult->isSuccess()) {
            $this->entityManager->flush();

            throw new CannotProcessTickBecauseSimulationFailedException($game, $simulationServiceResult->getError());
        }

        $this->entityManager->flush();

        return new PreviewTickResult($game, $simulationServiceResult->getResult());
    }

    public function getGameEvents(Uuid $gameId, int $limit, int $offset): GameEventsResult
    {
        $this->gameRepository->getGame($gameId);

        $events = $this->gameEventRepository->findByGamePaginated($gameId, $limit, $offset);
        $totalCount = $this->gameEventRepository->countByGame($gameId);

        return new GameEventsResult($events, $totalCount, $limit, $offset);
    }

    /**
     * @param array<int, Player> $players
     * @return array<int, PlayerRelationshipInput>
     */
    private function buildPlayerRelationshipData(array $players): array
    {
        $playerDataList = [];

        foreach ($players as $player) {
            $traitStrengths = [];

            foreach ($player->getPlayerTraits() as $playerTrait) {
                $traitStrengths[$playerTrait->getTraitDef()->getKey()] = $playerTrait->getStrength();
            }

            $playerDataList[] = new PlayerRelationshipInput(
                $player->getName(),
                $player->getDescription() ?? '',
                $traitStrengths,
            );
        }

        return $playerDataList;
    }
}
