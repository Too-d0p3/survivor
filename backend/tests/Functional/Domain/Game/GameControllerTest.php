<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\Game;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use App\Domain\Game\GameFacade;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Domain\User\User;
use App\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class GameControllerTest extends AbstractFunctionalTestCase
{
    public function testCreateGameWithoutAuthReturns401(): void
    {
        $this->jsonRequest('POST', '/api/game/create');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateGameReturnsGameWithPlayers(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $this->getBrowser()->loginUser($user);

        $this->jsonRequest('POST', '/api/game/create', [
            'playerName' => 'TestPlayer',
            'playerDescription' => 'A brave adventurer',
            'traitStrengths' => ['leadership' => '0.85', 'empathy' => '0.70'],
        ]);

        self::assertResponseIsSuccessful();

        $content = $this->getBrowser()->getResponse()->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $responseData */
        $responseData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('players', $responseData);
        self::assertIsArray($responseData['players']);
        self::assertCount(6, $responseData['players']);

        /** @var array<string, mixed> $humanPlayer */
        $humanPlayer = $responseData['players'][0];
        self::assertSame('TestPlayer', $humanPlayer['name']);
        self::assertTrue($humanPlayer['isHuman']);
        self::assertSame('A brave adventurer', $humanPlayer['description']);
        self::assertArrayHasKey('traits', $humanPlayer);

        /** @var array<string, mixed> $firstAiPlayer */
        $firstAiPlayer = $responseData['players'][1];
        self::assertSame('Alex', $firstAiPlayer['name']);
        self::assertFalse($firstAiPlayer['isHuman']);
        self::assertNotNull($firstAiPlayer['description']);
    }

    public function testCreateGameWithMissingPlayerNameReturns400(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $this->getBrowser()->loginUser($user);

        $this->jsonRequest('POST', '/api/game/create', [
            'playerDescription' => 'A brave adventurer',
            'traitStrengths' => ['leadership' => '0.85'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testStartGameReturnsGameWithTimeFields(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameId = $this->createGameViaFacade($user);

        $this->getBrowser()->loginUser($user);
        $this->jsonRequest('POST', "/api/game/{$gameId}/start");

        self::assertResponseIsSuccessful();

        $content = $this->getBrowser()->getResponse()->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $responseData */
        $responseData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($gameId, $responseData['id']);
        self::assertSame('in_progress', $responseData['status']);
        self::assertSame(1, $responseData['currentDay']);
        self::assertSame(6, $responseData['currentHour']);
        self::assertSame(0, $responseData['currentTick']);
        self::assertSame('morning', $responseData['dayPhase']);
        self::assertArrayHasKey('startedAt', $responseData);
    }

    public function testStartGameWithoutAuthReturns401(): void
    {
        $this->jsonRequest('POST', '/api/game/00000000-0000-0000-0000-000000000001/start');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testProcessTickReturnsUpdatedGameAndEvents(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameId = $this->createStartedGameViaFacade($user);

        $this->getBrowser()->loginUser($user);
        $this->jsonRequest('POST', "/api/game/{$gameId}/tick", [
            'actionText' => 'Went fishing',
        ]);

        self::assertResponseIsSuccessful();

        $content = $this->getBrowser()->getResponse()->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $responseData */
        $responseData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('game', $responseData);
        self::assertArrayHasKey('events', $responseData);

        /** @var array<string, mixed> $game */
        $game = $responseData['game'];
        self::assertSame(1, $game['currentTick']);
        self::assertSame(8, $game['currentHour']);
        self::assertSame('morning', $game['dayPhase']);

        self::assertIsArray($responseData['events']);
        self::assertNotEmpty($responseData['events']);
    }

    public function testProcessTickWithMissingActionTextReturns400(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameId = $this->createStartedGameViaFacade($user);

        $this->getBrowser()->loginUser($user);
        $this->jsonRequest('POST', "/api/game/{$gameId}/tick", []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testProcessTickWithoutAuthReturns401(): void
    {
        $this->jsonRequest('POST', '/api/game/00000000-0000-0000-0000-000000000001/tick', [
            'actionText' => 'Test action',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetGameEventsReturnsPaginatedEvents(): void
    {
        $this->setUpMockGeminiClient();
        $this->seedTraitDefs();
        $user = $this->createAndPersistUser();

        $gameId = $this->createStartedGameViaFacade($user);

        /** @var GameFacade $gameFacade */
        $gameFacade = self::getContainer()->get(GameFacade::class);
        $gameFacade->processTick(Uuid::fromString($gameId), $user, 'Action 1');
        $gameFacade->processTick(Uuid::fromString($gameId), $user, 'Action 2');

        $this->getBrowser()->loginUser($user);
        $this->getBrowser()->request('GET', "/api/game/{$gameId}/events?limit=2&offset=0");

        self::assertResponseIsSuccessful();

        $content = $this->getBrowser()->getResponse()->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $responseData */
        $responseData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('events', $responseData);
        self::assertArrayHasKey('pagination', $responseData);
        self::assertIsArray($responseData['events']);
        self::assertCount(2, $responseData['events']);

        /** @var array<string, mixed> $pagination */
        $pagination = $responseData['pagination'];
        self::assertSame(3, $pagination['totalCount']);
        self::assertSame(2, $pagination['limit']);
        self::assertSame(0, $pagination['offset']);
    }

    public function testGetGameEventsWithoutAuthReturns401(): void
    {
        $this->getBrowser()->request('GET', '/api/game/00000000-0000-0000-0000-000000000001/events');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function createGameViaFacade(User $user): string
    {
        /** @var GameFacade $gameFacade */
        $gameFacade = self::getContainer()->get(GameFacade::class);

        $result = $gameFacade->createGame($user, 'TestPlayer', 'A brave adventurer', ['leadership' => '0.85', 'empathy' => '0.70']);

        return $result->game->getId()->toString();
    }

    private function createStartedGameViaFacade(User $user): string
    {
        $gameId = $this->createGameViaFacade($user);

        /** @var GameFacade $gameFacade */
        $gameFacade = self::getContainer()->get(GameFacade::class);
        $gameFacade->startGame(Uuid::fromString($gameId), $user);

        return $gameId;
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
