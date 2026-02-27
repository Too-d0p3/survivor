<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Enum\DayPhase;
use App\Domain\Game\Enum\GameEventType;
use App\Domain\User\User;
use App\Dto\Game\CreateGameInput;
use App\Dto\Game\ProcessTickInput;
use App\Shared\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GameController extends AbstractApiController
{
    private readonly GameFacade $gameFacade;

    public function __construct(GameFacade $gameFacade)
    {
        $this->gameFacade = $gameFacade;
    }

    #[Route('/api/game/create', name: 'game_create', methods: ['POST'])]
    public function createGame(
        #[CurrentUser] ?User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $validationResult = $this->getValidatedDto($request, CreateGameInput::class, $serializer, $validator);

        if (!$validationResult->isValid()) {
            return $this->json(['errors' => $validationResult->errors], 400);
        }

        assert($validationResult->dto instanceof CreateGameInput);

        $result = $this->gameFacade->createGame(
            $user,
            $validationResult->dto->playerName,
            $validationResult->dto->playerDescription,
            $validationResult->dto->traitStrengths,
        );

        $game = $result->game;

        $players = [];
        foreach ($game->getPlayers() as $player) {
            $traits = [];
            foreach ($player->getPlayerTraits() as $playerTrait) {
                $traits[$playerTrait->getTraitDef()->getKey()] = $playerTrait->getStrength();
            }

            $players[] = [
                'id' => $player->getId()->toString(),
                'name' => $player->getName(),
                'isHuman' => $player->isHuman(),
                'description' => $player->getDescription(),
                'traits' => $traits,
            ];
        }

        return $this->json([
            'id' => $game->getId()->toString(),
            'players' => $players,
        ]);
    }

    #[Route('/api/game/{id}/start', name: 'game_start', methods: ['POST'])]
    public function startGame(
        string $id,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $gameId = Uuid::fromString($id);
        $result = $this->gameFacade->startGame($gameId, $user);

        $game = $result->game;

        /** @var int $currentHour */
        $currentHour = $game->getCurrentHour();

        return $this->json([
            'id' => $game->getId()->toString(),
            'status' => $game->getStatus()->value,
            'currentDay' => $game->getCurrentDay(),
            'currentHour' => $currentHour,
            'currentTick' => $game->getCurrentTick(),
            'dayPhase' => DayPhase::fromHour($currentHour)->value,
            'startedAt' => $game->getStartedAt()?->format('c'),
        ]);
    }

    #[Route('/api/game/{id}/tick', name: 'game_tick', methods: ['POST'])]
    public function processTick(
        string $id,
        #[CurrentUser] ?User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $validationResult = $this->getValidatedDto($request, ProcessTickInput::class, $serializer, $validator);

        if (!$validationResult->isValid()) {
            return $this->json(['errors' => $validationResult->errors], 400);
        }

        assert($validationResult->dto instanceof ProcessTickInput);

        $gameId = Uuid::fromString($id);
        $result = $this->gameFacade->processTick($gameId, $user, $validationResult->dto->actionText);

        $game = $result->game;

        /** @var int $tickCurrentHour */
        $tickCurrentHour = $game->getCurrentHour();

        $events = [];
        foreach ($result->events as $event) {
            $events[] = $this->serializeEvent($event);
        }

        return $this->json([
            'game' => [
                'id' => $game->getId()->toString(),
                'status' => $game->getStatus()->value,
                'currentDay' => $game->getCurrentDay(),
                'currentHour' => $tickCurrentHour,
                'currentTick' => $game->getCurrentTick(),
                'dayPhase' => DayPhase::fromHour($tickCurrentHour)->value,
            ],
            'events' => $events,
            'simulation' => $this->extractSimulationDebug($result->events),
        ]);
    }

    #[Route('/api/game/{id}/events', name: 'game_events', methods: ['GET'])]
    public function getGameEvents(
        string $id,
        #[CurrentUser] ?User $user,
        Request $request,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $gameId = Uuid::fromString($id);
        $limit = min((int) $request->query->get('limit', '20'), 100);
        $offset = max((int) $request->query->get('offset', '0'), 0);

        if ($limit < 1) {
            $limit = 20;
        }

        $result = $this->gameFacade->getGameEvents($gameId, $limit, $offset);

        $events = [];
        foreach ($result->events as $event) {
            $events[] = $this->serializeEvent($event);
        }

        return $this->json([
            'events' => $events,
            'pagination' => [
                'totalCount' => $result->totalCount,
                'limit' => $result->limit,
                'offset' => $result->offset,
            ],
        ]);
    }

    /**
     * @param array<int, GameEvent> $events
     * @return array<string, mixed>|null
     */
    private function extractSimulationDebug(array $events): ?array
    {
        $tickSimEvent = null;
        $perspectiveEvent = null;

        foreach ($events as $event) {
            if ($event->getType() === GameEventType::TickSimulation) {
                $tickSimEvent = $event;
            } elseif ($event->getType() === GameEventType::PlayerPerspective) {
                $perspectiveEvent = $event;
            }
        }

        if ($tickSimEvent === null) {
            return null;
        }

        $metadata = $tickSimEvent->getMetadata() ?? [];

        return [
            'reasoning' => $metadata['reasoning'] ?? null,
            'playerLocation' => $metadata['player_location'] ?? null,
            'playersNearby' => $metadata['players_nearby'] ?? [],
            'macroNarrative' => $tickSimEvent->getNarrative(),
            'playerNarrative' => $perspectiveEvent?->getNarrative(),
            'relationshipChanges' => $metadata['relationship_changes'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvent(GameEvent $event): array
    {
        return [
            'id' => $event->getId()->toString(),
            'type' => $event->getType()->value,
            'day' => $event->getDay(),
            'hour' => $event->getHour(),
            'tick' => $event->getTick(),
            'dayPhase' => DayPhase::fromHour($event->getHour())->value,
            'playerId' => $event->getPlayer()?->getId()->toString(),
            'playerName' => $event->getPlayer()?->getName(),
            'narrative' => $event->getNarrative(),
            'metadata' => $event->getMetadata(),
            'createdAt' => $event->getCreatedAt()->format('c'),
        ];
    }
}
