<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Game;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseUserIsNotPlayerException;
use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;
use App\Domain\Game\GameFacade;
use App\Domain\Game\GameStatus;
use App\Domain\Relationship\Relationship;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Tests\Integration\AbstractIntegrationTestCase;

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

    public function testGetGameEventsReturnsPaginatedResults(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameFacade = $this->getService(GameFacade::class);
        $createResult = $gameFacade->createGame($user, 'Human', 'A strategic player', ['leadership' => '0.85']);
        $gameFacade->startGame($createResult->game->getId(), $user);
        $gameFacade->processTick($createResult->game->getId(), $user, 'Action 1');
        $gameFacade->processTick($createResult->game->getId(), $user, 'Action 2');

        $eventsResult = $gameFacade->getGameEvents($createResult->game->getId(), 2, 0);

        self::assertCount(2, $eventsResult->events);
        self::assertSame(3, $eventsResult->totalCount);
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

                return $this->buildRelationshipsResponse();
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
