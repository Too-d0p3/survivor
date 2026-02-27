<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Ai\Operation\PlayerRelationshipInput;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseUserIsNotPlayerException;
use App\Domain\Game\Result\CreateGameResult;
use App\Domain\Game\Result\GameEventsResult;
use App\Domain\Game\Result\ProcessTickResult;
use App\Domain\Game\Result\StartGameResult;
use App\Domain\Player\Player;
use App\Domain\Player\PlayerRepository;
use App\Domain\Player\PlayerService;
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

    public function __construct(
        EntityManagerInterface $entityManager,
        GameService $gameService,
        GameRepository $gameRepository,
        GameEventRepository $gameEventRepository,
        PlayerService $playerService,
        PlayerRepository $playerRepository,
        RelationshipService $relationshipService,
        TraitDefRepository $traitDefRepository,
    ) {
        $this->entityManager = $entityManager;
        $this->gameService = $gameService;
        $this->gameRepository = $gameRepository;
        $this->gameEventRepository = $gameEventRepository;
        $this->playerService = $playerService;
        $this->playerRepository = $playerRepository;
        $this->relationshipService = $relationshipService;
        $this->traitDefRepository = $traitDefRepository;
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

        $result = $this->gameService->processTick($game, $humanPlayer, $actionText, $now);

        foreach ($result->events as $event) {
            $this->entityManager->persist($event);
        }

        $this->entityManager->flush();

        return $result;
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
