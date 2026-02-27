<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Game;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseSimulationFailedException;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseUserIsNotPlayerException;
use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;
use App\Domain\Game\GameFacade;
use App\Domain\Game\GameStatus;
use App\Domain\Relationship\Relationship;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Tests\Integration\AbstractIntegrationTestCase;
use RuntimeException;

final class GameFacadeTest extends AbstractIntegrationTestCase
{
    public function testCreateGamePersistsGameWithSixPlayers(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $result = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85', 'empathy' => '0.70']);

        $foundGame = $this->getEntityManager()->find(Game::class, $result->game->getId());
        self::assertNotNull($foundGame);
        self::assertCount(6, $foundGame->getPlayers());
    }

    public function testCreateGameHumanPlayerIsLinkedToUser(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $result = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);

        $humanPlayer = $result->game->getPlayers()[0];

        self::assertTrue($humanPlayer->isHuman());
        self::assertSame($user, $humanPlayer->getUser());
        self::assertSame('Human', $humanPlayer->getName());
    }

    public function testCreateGameAiPlayersHaveRandomTraits(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $result = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);

        $aiPlayers = array_slice($result->game->getPlayers(), 1);

        foreach ($aiPlayers as $aiPlayer) {
            self::assertFalse($aiPlayer->isHuman());
            self::assertCount(2, $aiPlayer->getPlayerTraits());

            foreach ($aiPlayer->getPlayerTraits() as $trait) {
                $value = (float) $trait->getStrength();
                self::assertGreaterThanOrEqual(0.0, $value);
                self::assertLessThanOrEqual(1.0, $value);
            }
        }
    }

    public function testCreateGameAiPlayersHaveGeneratedDescriptions(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $result = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);

        $aiPlayers = array_slice($result->game->getPlayers(), 1);

        self::assertSame('AI player 1 summary.', $aiPlayers[0]->getDescription());
        self::assertSame('AI player 2 summary.', $aiPlayers[1]->getDescription());
        self::assertSame('AI player 3 summary.', $aiPlayers[2]->getDescription());
        self::assertSame('AI player 4 summary.', $aiPlayers[3]->getDescription());
        self::assertSame('AI player 5 summary.', $aiPlayers[4]->getDescription());
    }

    public function testCreateGamePersistsAiLogs(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);

        $aiLogRepository = $this->getEntityManager()->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'generateBatchPlayerTraitsSummaryDescriptions']);

        self::assertCount(1, $logs);
    }

    public function testCreateGamePersistsRelationshipsForAllPlayerPairs(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85', 'empathy' => '0.70']);

        $relationshipRepository = $this->getEntityManager()->getRepository(Relationship::class);
        $relationships = $relationshipRepository->findAll();

        self::assertCount(30, $relationships);
    }

    public function testCreateGameRelationshipsHaveAiGeneratedScores(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85', 'empathy' => '0.70']);

        $relationshipRepository = $this->getEntityManager()->getRepository(Relationship::class);
        $relationships = $relationshipRepository->findAll();

        $hasNonDefaultScore = false;

        foreach ($relationships as $relationship) {
            if (
                $relationship->getTrust() !== 50 || $relationship->getAffinity() !== 50
                || $relationship->getRespect() !== 50 || $relationship->getThreat() !== 50
            ) {
                $hasNonDefaultScore = true;

                break;
            }
        }

        self::assertTrue($hasNonDefaultScore, 'At least one relationship should have non-default scores');
    }

    public function testCreateGamePersistsRelationshipAiLog(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);

        $aiLogRepository = $this->getEntityManager()->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'initializeRelationships']);

        self::assertCount(1, $logs);
    }

    public function testStartGamePersistsGameInProgressWithTimeFields(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);

        $startResult = $gameFacade->startGame($createResult->game->getId(), $user);

        $foundGame = $this->getEntityManager()->find(Game::class, $startResult->game->getId());
        self::assertNotNull($foundGame);
        self::assertSame(GameStatus::InProgress, $foundGame->getStatus());
        self::assertSame(1, $foundGame->getCurrentDay());
        self::assertSame(6, $foundGame->getCurrentHour());
        self::assertSame(0, $foundGame->getCurrentTick());
        self::assertNotNull($foundGame->getStartedAt());
    }

    public function testStartGamePersistsGameStartedEvent(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);

        $gameFacade->startGame($createResult->game->getId(), $user);

        $eventRepository = $this->getEntityManager()->getRepository(GameEvent::class);
        $events = $eventRepository->findBy(['game' => $createResult->game->getId()]);

        self::assertCount(1, $events);
        self::assertSame(GameEventType::GameStarted, $events[0]->getType());
    }

    public function testProcessTickPersistsPlayerActionEvent(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $tickResult = $gameFacade->processTick($createResult->game->getId(), $user, 'Went fishing');

        $playerActionEvents = array_filter(
            $tickResult->events,
            static fn (GameEvent $e) => $e->getType() === GameEventType::PlayerAction,
        );
        self::assertCount(1, $playerActionEvents);

        $event = reset($playerActionEvents);
        self::assertSame(['action_text' => 'Went fishing'], $event->getMetadata());
    }

    public function testProcessTickCreatesSimulationEvents(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $tickResult = $gameFacade->processTick($createResult->game->getId(), $user, 'Went fishing');

        $tickSimEvents = array_filter(
            $tickResult->events,
            static fn (GameEvent $e) => $e->getType() === GameEventType::TickSimulation,
        );
        self::assertCount(1, $tickSimEvents);

        $perspectiveEvents = array_filter(
            $tickResult->events,
            static fn (GameEvent $e) => $e->getType() === GameEventType::PlayerPerspective,
        );
        self::assertCount(1, $perspectiveEvents);

        $tickSimEvent = reset($tickSimEvents);
        self::assertNull($tickSimEvent->getPlayer());
        self::assertNotNull($tickSimEvent->getNarrative());

        $perspectiveEvent = reset($perspectiveEvents);
        self::assertNotNull($perspectiveEvent->getPlayer());
        self::assertNotNull($perspectiveEvent->getNarrative());
    }

    public function testProcessTickAdjustsRelationships(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        // The mock simulation returns relationship changes for source_index=1, target_index=2
        $gameFacade->processTick($createResult->game->getId(), $user, 'Went fishing');

        $relationshipRepository = $this->getEntityManager()->getRepository(Relationship::class);
        $relationships = $relationshipRepository->findAll();

        // Verify at least one relationship was updated (updatedAt changed)
        $hasUpdated = false;
        foreach ($relationships as $relationship) {
            if ($relationship->getUpdatedAt() > $relationship->getCreatedAt()) {
                $hasUpdated = true;

                break;
            }
        }

        self::assertTrue($hasUpdated, 'At least one relationship should have been updated by simulation');
    }

    public function testProcessTickPersistsSimulationAiLog(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $gameFacade->processTick($createResult->game->getId(), $user, 'Went fishing');

        $aiLogRepository = $this->getEntityManager()->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'simulateTick']);

        self::assertCount(1, $logs);
    }

    public function testProcessTickAdvancesGameTime(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $gameFacade->processTick($createResult->game->getId(), $user, 'Went fishing');

        $foundGame = $this->getEntityManager()->find(Game::class, $createResult->game->getId());
        self::assertNotNull($foundGame);
        self::assertSame(1, $foundGame->getCurrentTick());
        self::assertSame(8, $foundGame->getCurrentHour());
    }

    public function testProcessTickAtNightPersistsNightSleepAndAdvancesDay(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        // Advance through 8 ticks (hours: 6->8->10->12->14->16->18->20->22)
        for ($i = 0; $i < 8; $i++) {
            $gameFacade->processTick($createResult->game->getId(), $user, "Action {$i}");
        }

        // 9th tick at hour 22 should trigger night sleep
        $result = $gameFacade->processTick($createResult->game->getId(), $user, 'Last action');

        $nightSleepEvents = array_filter(
            $result->events,
            static fn (GameEvent $e) => $e->getType() === GameEventType::NightSleep,
        );
        self::assertCount(1, $nightSleepEvents);

        $foundGame = $this->getEntityManager()->find(Game::class, $createResult->game->getId());
        self::assertNotNull($foundGame);
        self::assertSame(2, $foundGame->getCurrentDay());
        self::assertSame(6, $foundGame->getCurrentHour());
    }

    public function testProcessTickSimulationFailureThrowsExceptionAndPersistsLog(): void
    {
        $this->setUpFailingSimulationMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        try {
            $gameFacade->processTick($createResult->game->getId(), $user, 'Went fishing');
            self::fail('Expected CannotProcessTickBecauseSimulationFailedException');
        } catch (CannotProcessTickBecauseSimulationFailedException) {
            // Expected
        }

        // AI log should still be persisted even on failure
        $aiLogRepository = $this->getEntityManager()->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'simulateTick']);

        self::assertCount(1, $logs);
    }

    public function testGetGameEventsReturnsPaginatedResults(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);
        $gameFacade->processTick($createResult->game->getId(), $user, 'Action 1');

        $eventsResult = $gameFacade->getGameEvents($createResult->game->getId(), 2, 0);

        self::assertCount(2, $eventsResult->events);
        // GameStarted (tick 0) + PlayerAction (tick 0) + TickSimulation (tick 0) + PlayerPerspective (tick 0) = 4 events
        self::assertSame(4, $eventsResult->totalCount);
        self::assertSame(2, $eventsResult->limit);
        self::assertSame(0, $eventsResult->offset);
    }

    public function testProcessTickWhenUserIsNotPlayerThrowsException(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();
        $otherUser = $this->createAndPersistUser('other@example.com');

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $this->expectException(CannotProcessTickBecauseUserIsNotPlayerException::class);

        $gameFacade->processTick($createResult->game->getId(), $otherUser, 'Action');
    }

    public function testPreviewTickReturnsSimulationResult(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $result = $gameFacade->previewTick($createResult->game->getId(), $user, 'Went fishing');

        self::assertNotEmpty($result->game->getId()->toRfc4122());
        self::assertNotEmpty($result->simulation->getReasoning());
        self::assertNotEmpty($result->simulation->getMacroNarrative());
        self::assertNotEmpty($result->simulation->getPlayerNarrative());
        self::assertNotEmpty($result->simulation->getPlayerLocation());
    }

    public function testPreviewTickDoesNotAdvanceGameTime(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $gameFacade->previewTick($createResult->game->getId(), $user, 'Went fishing');

        $foundGame = $this->getEntityManager()->find(Game::class, $createResult->game->getId());
        self::assertNotNull($foundGame);
        self::assertSame(1, $foundGame->getCurrentDay());
        self::assertSame(6, $foundGame->getCurrentHour());
        self::assertSame(0, $foundGame->getCurrentTick());
    }

    public function testPreviewTickDoesNotCreateAnyGameEvents(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $gameFacade->previewTick($createResult->game->getId(), $user, 'Went fishing');

        $eventRepository = $this->getEntityManager()->getRepository(GameEvent::class);
        $events = $eventRepository->findBy(['game' => $createResult->game->getId()]);

        // Only the GameStarted event from startGame() should exist
        self::assertCount(1, $events);
        self::assertSame(GameEventType::GameStarted, $events[0]->getType());
    }

    public function testPreviewTickDoesNotMutateRelationships(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        // Capture relationship scores before preview
        $relationshipRepository = $this->getEntityManager()->getRepository(Relationship::class);
        $relationshipsBefore = $relationshipRepository->findAll();

        /** @var array<string, array{trust: int, affinity: int, respect: int, threat: int}> $scoresBefore */
        $scoresBefore = [];
        foreach ($relationshipsBefore as $rel) {
            $key = $rel->getSource()->getId()->toString() . '->' . $rel->getTarget()->getId()->toString();
            $scoresBefore[$key] = [
                'trust' => $rel->getTrust(),
                'affinity' => $rel->getAffinity(),
                'respect' => $rel->getRespect(),
                'threat' => $rel->getThreat(),
            ];
        }

        $gameFacade->previewTick($createResult->game->getId(), $user, 'Went fishing');

        // Clear entity manager to force fresh load from DB
        $this->getEntityManager()->clear();

        $relationshipsAfter = $this->getEntityManager()->getRepository(Relationship::class)->findAll();
        foreach ($relationshipsAfter as $rel) {
            $key = $rel->getSource()->getId()->toString() . '->' . $rel->getTarget()->getId()->toString();
            self::assertArrayHasKey($key, $scoresBefore);
            self::assertSame($scoresBefore[$key]['trust'], $rel->getTrust(), "Trust changed for {$key}");
            self::assertSame($scoresBefore[$key]['affinity'], $rel->getAffinity(), "Affinity changed for {$key}");
            self::assertSame($scoresBefore[$key]['respect'], $rel->getRespect(), "Respect changed for {$key}");
            self::assertSame($scoresBefore[$key]['threat'], $rel->getThreat(), "Threat changed for {$key}");
        }
    }

    public function testPreviewTickPersistsAiLog(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $gameFacade->previewTick($createResult->game->getId(), $user, 'Went fishing');

        $aiLogRepository = $this->getEntityManager()->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'simulateTick']);

        self::assertCount(1, $logs);
    }

    public function testPreviewTickWhenUserIsNotPlayerThrowsException(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();
        $otherUser = $this->createAndPersistUser('other@example.com');

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        $this->expectException(CannotProcessTickBecauseUserIsNotPlayerException::class);

        $gameFacade->previewTick($createResult->game->getId(), $otherUser, 'Action');
    }

    public function testPreviewTickWhenSimulationFailsThrowsExceptionAndPersistsLog(): void
    {
        $this->setUpFailingSimulationMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);

        try {
            $gameFacade->previewTick($createResult->game->getId(), $user, 'Went fishing');
            self::fail('Expected CannotProcessTickBecauseSimulationFailedException');
        } catch (CannotProcessTickBecauseSimulationFailedException) {
            // Expected
        }

        $aiLogRepository = $this->getEntityManager()->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'simulateTick']);

        self::assertCount(1, $logs);
    }

    private function seedTraitDefs(): void
    {
        $entityManager = $this->getEntityManager();

        $leadership = new TraitDef('leadership', 'Leadership', 'Leading ability', TraitType::Social);
        $empathy = new TraitDef('empathy', 'Empathy', 'Understanding others', TraitType::Emotional);
        $entityManager->persist($leadership);
        $entityManager->persist($empathy);
        $entityManager->flush();
    }

    private function setUpMockGeminiClient(): void
    {
        $mockClient = new class implements GeminiClient {
            private int $callCount = 0;

            public function request(AiRequest $aiRequest): AiResponse
            {
                $this->callCount++;

                if ($this->callCount === 1) {
                    return $this->buildSummariesResponse();
                }

                if ($this->callCount === 2) {
                    return $this->buildRelationshipsResponse();
                }

                // All subsequent calls are simulation ticks
                return $this->buildSimulationResponse();
            }

            private function buildSummariesResponse(): AiResponse
            {
                $summaries = [];
                for ($i = 1; $i <= 5; $i++) {
                    $summaries[] = ['player_index' => $i, 'summary' => "AI player {$i} summary."];
                }

                return new AiResponse(
                    json_encode(['summaries' => $summaries], JSON_THROW_ON_ERROR),
                    new TokenUsage(100, 50, 150),
                    200,
                    'gemini-2.5-flash',
                    '{"candidates": []}',
                    'STOP',
                );
            }

            private function buildRelationshipsResponse(): AiResponse
            {
                $relationships = [];
                $playerCount = 6;

                for ($source = 1; $source <= $playerCount; $source++) {
                    for ($target = 1; $target <= $playerCount; $target++) {
                        if ($source === $target) {
                            continue;
                        }

                        $relationships[] = [
                            'source_index' => $source,
                            'target_index' => $target,
                            'trust' => 40 + $source + $target,
                            'affinity' => 45 + $source,
                            'respect' => 50 + $target,
                            'threat' => 30 + $source * 2,
                        ];
                    }
                }

                return new AiResponse(
                    json_encode(['relationships' => $relationships], JSON_THROW_ON_ERROR),
                    new TokenUsage(200, 100, 300),
                    200,
                    'gemini-2.5-flash',
                    '{"candidates": []}',
                    'STOP',
                );
            }

            private function buildSimulationResponse(): AiResponse
            {
                return new AiResponse(
                    json_encode([
                        'reasoning' => 'Hráči trávili čas na ostrově, diskutovali a sbírali zásoby.',
                        'player_location' => 'pláž',
                        'players_nearby' => [2, 3],
                        'macro_narrative' => 'Human se vydal na pláž, kde potkal Alexe a Báru. Společně diskutovali o zásobách jídla. Cyril mezitím prozkoumával vnitrozemí ostrova a Dana s Emilem stavěli přístřešek.',
                        'player_narrative' => 'Vydal ses na pláž, kde jsi potkal Alexe a Báru. Společně jste diskutovali o tom, jak rozdělit zásoby jídla.',
                        'relationship_changes' => [
                            ['source_index' => 1, 'target_index' => 2, 'trust_delta' => 3, 'affinity_delta' => 2, 'respect_delta' => 0, 'threat_delta' => 0],
                            ['source_index' => 2, 'target_index' => 1, 'trust_delta' => 2, 'affinity_delta' => 1, 'respect_delta' => 1, 'threat_delta' => 0],
                        ],
                    ], JSON_THROW_ON_ERROR),
                    new TokenUsage(300, 200, 500),
                    300,
                    'gemini-2.5-flash',
                    '{"candidates": []}',
                    'STOP',
                );
            }
        };

        self::getContainer()->set(GeminiClient::class, $mockClient);
    }

    private function setUpFailingSimulationMockGeminiClient(): void
    {
        $mockClient = new class implements GeminiClient {
            private int $callCount = 0;

            public function request(AiRequest $aiRequest): AiResponse
            {
                $this->callCount++;

                if ($this->callCount === 1) {
                    return $this->buildSummariesResponse();
                }

                if ($this->callCount === 2) {
                    return $this->buildRelationshipsResponse();
                }

                // Simulation calls fail with invalid JSON
                throw new RuntimeException('AI service unavailable');
            }

            private function buildSummariesResponse(): AiResponse
            {
                $summaries = [];
                for ($i = 1; $i <= 5; $i++) {
                    $summaries[] = ['player_index' => $i, 'summary' => "AI player {$i} summary."];
                }

                return new AiResponse(
                    json_encode(['summaries' => $summaries], JSON_THROW_ON_ERROR),
                    new TokenUsage(100, 50, 150),
                    200,
                    'gemini-2.5-flash',
                    '{"candidates": []}',
                    'STOP',
                );
            }

            private function buildRelationshipsResponse(): AiResponse
            {
                $relationships = [];
                $playerCount = 6;

                for ($source = 1; $source <= $playerCount; $source++) {
                    for ($target = 1; $target <= $playerCount; $target++) {
                        if ($source === $target) {
                            continue;
                        }

                        $relationships[] = [
                            'source_index' => $source,
                            'target_index' => $target,
                            'trust' => 40 + $source + $target,
                            'affinity' => 45 + $source,
                            'respect' => 50 + $target,
                            'threat' => 30 + $source * 2,
                        ];
                    }
                }

                return new AiResponse(
                    json_encode(['relationships' => $relationships], JSON_THROW_ON_ERROR),
                    new TokenUsage(200, 100, 300),
                    200,
                    'gemini-2.5-flash',
                    '{"candidates": []}',
                    'STOP',
                );
            }
        };

        self::getContainer()->set(GeminiClient::class, $mockClient);
    }
}
