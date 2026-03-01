<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Ai\Result\MajorEventData;
use App\Domain\Ai\Result\MajorEventParticipantData;
use App\Domain\Ai\Result\RelationshipDelta;
use App\Domain\Ai\Result\SimulateTickResult;
use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Enum\MajorEventType;
use App\Domain\Game\Enum\ParticipantRole;
use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;
use App\Domain\Game\GameStatus;
use App\Domain\Game\SimulationService;
use App\Domain\Player\Player;
use App\Domain\Relationship\Relationship;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SimulationServiceTest extends TestCase
{
    private SimulationService $service;

    public function testApplySimulationCreatesTickSimulationEvent(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $now = new DateTimeImmutable();

        $simulationResult = $this->createSimulationResult();

        $result = $this->service->applySimulation($game, $simulationResult, $players, [], 1, 8, 1, $now);

        $tickSimEvents = array_filter(
            $result->events,
            static fn ($e) => $e->getType() === GameEventType::TickSimulation,
        );
        self::assertCount(1, $tickSimEvents);

        $event = reset($tickSimEvents);
        self::assertNull($event->getPlayer());
        self::assertSame('Ondra se vydal k lesu. Alex a Dana vařili u ohně.', $event->getNarrative());
        self::assertSame(1, $event->getDay());
        self::assertSame(8, $event->getHour());
        self::assertSame(1, $event->getTick());
    }

    public function testApplySimulationCreatesPlayerPerspectiveEvent(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $now = new DateTimeImmutable();

        $simulationResult = $this->createSimulationResult();

        $result = $this->service->applySimulation($game, $simulationResult, $players, [], 1, 8, 1, $now);

        $perspectiveEvents = array_filter(
            $result->events,
            static fn ($e) => $e->getType() === GameEventType::PlayerPerspective,
        );
        self::assertCount(1, $perspectiveEvents);

        $event = reset($perspectiveEvents);
        self::assertSame($players[0], $event->getPlayer());
        self::assertSame('Vydal ses k lesu sbírat dříví.', $event->getNarrative());
    }

    public function testApplySimulationAdjustsRelationships(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $now = new DateTimeImmutable();
        $createdAt = new DateTimeImmutable('2026-01-01');

        $relationship = new Relationship($players[0], $players[1], 50, 50, 50, 50, $createdAt);

        $simulationResult = new SimulateTickResult(
            'test reasoning',
            'pláž',
            [2],
            'macro narativ',
            'player narativ',
            [new RelationshipDelta(1, 2, 5, -3, 2, 0)],
        );

        $this->service->applySimulation($game, $simulationResult, $players, [$relationship], 1, 8, 1, $now);

        self::assertSame(55, $relationship->getTrust());
        self::assertSame(47, $relationship->getAffinity());
        self::assertSame(52, $relationship->getRespect());
        self::assertSame(50, $relationship->getThreat());
    }

    public function testApplySimulationClampsDeltaToMax15(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $now = new DateTimeImmutable();
        $createdAt = new DateTimeImmutable('2026-01-01');

        $relationship = new Relationship($players[0], $players[1], 50, 50, 50, 50, $createdAt);

        $simulationResult = new SimulateTickResult(
            'test reasoning',
            'pláž',
            [2],
            'macro narativ',
            'player narativ',
            [new RelationshipDelta(1, 2, 20, -20, 18, -16)],
        );

        $this->service->applySimulation($game, $simulationResult, $players, [$relationship], 1, 8, 1, $now);

        // Deltas should be clamped to ±15
        self::assertSame(65, $relationship->getTrust());   // 50 + 15
        self::assertSame(35, $relationship->getAffinity()); // 50 - 15
        self::assertSame(65, $relationship->getRespect());  // 50 + 15
        self::assertSame(35, $relationship->getThreat());   // 50 - 15
    }

    public function testApplySimulationWithEmptyChangesStillCreatesEvents(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $now = new DateTimeImmutable();

        $simulationResult = new SimulateTickResult(
            'klidný den',
            'pláž',
            [],
            'Všichni relaxovali.',
            'Ležel jsi na pláži.',
            [],
        );

        $result = $this->service->applySimulation($game, $simulationResult, $players, [], 1, 8, 1, $now);

        self::assertCount(2, $result->events);
        self::assertSame(GameEventType::TickSimulation, $result->events[0]->getType());
        self::assertSame(GameEventType::PlayerPerspective, $result->events[1]->getType());
    }

    public function testApplySimulationTickSimulationMetadataContainsChanges(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $now = new DateTimeImmutable();

        $simulationResult = new SimulateTickResult(
            'rozvaha o hráčích',
            'okraj lesa',
            [2, 3],
            'macro narativ',
            'player narativ',
            [new RelationshipDelta(1, 2, 5, -3, 0, 0)],
        );

        $result = $this->service->applySimulation($game, $simulationResult, $players, [], 1, 8, 1, $now);

        $tickSimEvent = $result->events[0];
        $metadata = $tickSimEvent->getMetadata();

        self::assertNotNull($metadata);
        self::assertSame('rozvaha o hráčích', $metadata['reasoning']);
        self::assertSame('okraj lesa', $metadata['player_location']);
        self::assertSame([2, 3], $metadata['players_nearby']);

        self::assertIsArray($metadata['relationship_changes']);
        /** @var array<int, array<string, int>> $relationshipChanges */
        $relationshipChanges = $metadata['relationship_changes'];
        self::assertCount(1, $relationshipChanges);
        self::assertSame(1, $relationshipChanges[0]['source_index']);
        self::assertSame(2, $relationshipChanges[0]['target_index']);
    }

    public function testApplySimulationIgnoresUnknownRelationshipPairs(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $now = new DateTimeImmutable();

        // Relationship for players 1->2 exists, but delta references 1->3 (no relationship entity)
        $relationship = new Relationship($players[0], $players[1], 50, 50, 50, 50, new DateTimeImmutable('2026-01-01'));

        $simulationResult = new SimulateTickResult(
            'test',
            'pláž',
            [3],
            'macro',
            'player',
            [new RelationshipDelta(1, 3, 5, 0, 0, 0)],
        );

        $this->service->applySimulation($game, $simulationResult, $players, [$relationship], 1, 8, 1, $now);

        // Relationship 1->2 should remain unchanged
        self::assertSame(50, $relationship->getTrust());
    }

    public function testExtractMajorEventsHappyPath(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEventsData = [
            new MajorEventData(
                'alliance',
                'Alex a Bara uzavřeli alianci.',
                7,
                [
                    new MajorEventParticipantData(2, 'initiator'),
                    new MajorEventParticipantData(3, 'target'),
                ],
            ),
        ];

        $result = $this->service->extractMajorEvents($game, $sourceEvent, $majorEventsData, $players, [], 1, 8, 1, $now);

        self::assertCount(1, $result->majorEvents);
        $majorEvent = $result->majorEvents[0];
        self::assertSame(MajorEventType::Alliance, $majorEvent->getType());
        self::assertSame('Alex a Bara uzavřeli alianci.', $majorEvent->getSummary());
        self::assertSame(7, $majorEvent->getEmotionalWeight());
        self::assertCount(2, $majorEvent->getParticipants());
        self::assertSame($players[1], $majorEvent->getParticipants()[0]->getPlayer());
        self::assertSame(ParticipantRole::Initiator, $majorEvent->getParticipants()[0]->getRole());
        self::assertSame($players[2], $majorEvent->getParticipants()[1]->getPlayer());
        self::assertSame(ParticipantRole::Target, $majorEvent->getParticipants()[1]->getRole());
    }

    public function testExtractMajorEventsInvalidTypeSkipped(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEventsData = [
            new MajorEventData(
                'invalid_type',
                'Neznámá událost.',
                5,
                [new MajorEventParticipantData(2, 'initiator')],
            ),
        ];

        $result = $this->service->extractMajorEvents($game, $sourceEvent, $majorEventsData, $players, [], 1, 8, 1, $now);

        self::assertSame([], $result->majorEvents);
    }

    public function testExtractMajorEventsInvalidPlayerIndexSkipped(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEventsData = [
            new MajorEventData(
                'conflict',
                'Konflikt.',
                5,
                [
                    new MajorEventParticipantData(99, 'initiator'),
                    new MajorEventParticipantData(2, 'target'),
                ],
            ),
        ];

        $result = $this->service->extractMajorEvents($game, $sourceEvent, $majorEventsData, $players, [], 1, 8, 1, $now);

        // Event kept, but only the valid participant (player index 2)
        self::assertCount(1, $result->majorEvents);
        self::assertCount(1, $result->majorEvents[0]->getParticipants());
        self::assertSame($players[1], $result->majorEvents[0]->getParticipants()[0]->getPlayer());
    }

    public function testExtractMajorEventsAllParticipantsInvalidSkipsEvent(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEventsData = [
            new MajorEventData(
                'conflict',
                'Konflikt.',
                5,
                [
                    new MajorEventParticipantData(99, 'initiator'),
                    new MajorEventParticipantData(100, 'target'),
                ],
            ),
        ];

        $result = $this->service->extractMajorEvents($game, $sourceEvent, $majorEventsData, $players, [], 1, 8, 1, $now);

        self::assertSame([], $result->majorEvents);
    }

    public function testExtractMajorEventsDuplicateSummarySkipped(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEventsData = [
            new MajorEventData(
                'alliance',
                'Alex a Bara uzavřeli alianci',
                7,
                [new MajorEventParticipantData(2, 'initiator')],
            ),
        ];

        // Existing summary is a superstring of the new summary (contains it)
        $existingSummaries = ['Alex a Bara uzavřeli alianci pro hlasování'];

        $result = $this->service->extractMajorEvents($game, $sourceEvent, $majorEventsData, $players, $existingSummaries, 1, 8, 1, $now);

        self::assertSame([], $result->majorEvents);
    }

    public function testExtractMajorEventsClampingAndTruncation(): void
    {
        $game = $this->createStartedGame();
        $players = $this->createPlayers($game);
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();
        $longSummary = str_repeat('z', 250);

        $majorEventsData = [
            new MajorEventData(
                'other',
                $longSummary,
                15,
                [new MajorEventParticipantData(2, 'witness')],
            ),
        ];

        $result = $this->service->extractMajorEvents($game, $sourceEvent, $majorEventsData, $players, [], 1, 8, 1, $now);

        self::assertCount(1, $result->majorEvents);
        self::assertSame(200, mb_strlen($result->majorEvents[0]->getSummary()));
        self::assertSame(10, $result->majorEvents[0]->getEmotionalWeight());
    }

    protected function setUp(): void
    {
        $this->service = new SimulationService();
    }

    private function createSourceEvent(Game $game): GameEvent
    {
        return new GameEvent(
            $game,
            GameEventType::TickSimulation,
            1,
            8,
            1,
            new DateTimeImmutable(),
        );
    }

    private function createStartedGame(): Game
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $game->start(new DateTimeImmutable());

        return $game;
    }

    /**
     * @return array<int, Player>
     */
    private function createPlayers(Game $game): array
    {
        $owner = $game->getOwner();

        return [
            new Player('Ondra', $game, $owner),
            new Player('Alex', $game),
            new Player('Bara', $game),
        ];
    }

    private function createSimulationResult(): SimulateTickResult
    {
        return new SimulateTickResult(
            'Ondra šel sbírat dříví, Alex a Dana vařili.',
            'okraj lesa',
            [2],
            'Ondra se vydal k lesu. Alex a Dana vařili u ohně.',
            'Vydal ses k lesu sbírat dříví.',
            [],
        );
    }
}
