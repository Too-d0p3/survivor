<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Game;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use App\Domain\Game\Game;
use App\Domain\Game\GameFacade;
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
            public function request(AiRequest $aiRequest): AiResponse
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
        };

        self::getContainer()->set(GeminiClient::class, $mockClient);
    }
}
