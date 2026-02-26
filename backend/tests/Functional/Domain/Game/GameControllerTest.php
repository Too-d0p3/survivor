<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\Game;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

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
