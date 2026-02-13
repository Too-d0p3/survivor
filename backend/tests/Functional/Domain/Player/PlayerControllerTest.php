<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\Player;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PlayerControllerTest extends AbstractFunctionalTestCase
{
    public function testGenerateTraitsReturnsTraitsAndSummary(): void
    {
        $user = $this->createAndPersistUser('test@example.com', 'password123');

        $this->setUpMockGeminiClient();

        $entityManager = $this->getEntityManager();
        $leadership = new TraitDef('leadership', 'Leadership', 'Leading ability', TraitType::Social);
        $empathy = new TraitDef('empathy', 'Empathy', 'Understanding others', TraitType::Emotional);
        $entityManager->persist($leadership);
        $entityManager->persist($empathy);
        $entityManager->flush();

        $this->getBrowser()->loginUser($user);

        $this->jsonRequest('POST', '/api/game/player/traits/generate', [
            'description' => 'A strong leader with empathy',
        ]);

        self::assertResponseIsSuccessful();

        $content = $this->getBrowser()->getResponse()->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $responseData */
        $responseData = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('traits', $responseData);
        self::assertArrayHasKey('summary', $responseData);
        self::assertSame(['leadership' => 0.85, 'empathy' => 0.6], $responseData['traits']);
        self::assertSame('Test summary.', $responseData['summary']);
    }

    public function testGenerateTraitsWithoutAuthReturns401(): void
    {
        $this->jsonRequest('POST', '/api/game/player/traits/generate', [
            'description' => 'A strong leader',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGenerateSummaryDescriptionReturns200(): void
    {
        $user = $this->createAndPersistUser('test@example.com', 'password123');

        $this->setUpMockGeminiClientForSummary();

        $this->getBrowser()->loginUser($user);

        $this->jsonRequest('POST', '/api/game/player/traits/generate-summary-description', [
            'leadership' => '0.85',
            'empathy' => '0.6',
        ]);

        self::assertResponseIsSuccessful();

        $content = $this->getBrowser()->getResponse()->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $responseData */
        $responseData = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('summary', $responseData);
        self::assertSame('Summary from traits.', $responseData['summary']);
    }

    public function testGenerateSummaryDescriptionWithoutAuthReturns401(): void
    {
        $this->jsonRequest('POST', '/api/game/player/traits/generate-summary-description', [
            'leadership' => '0.85',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGenerateTraitsWithEmptyDescriptionReturns400(): void
    {
        $user = $this->createAndPersistUser('test@example.com', 'password123');
        $this->getBrowser()->loginUser($user);

        $this->setUpMockGeminiClient();

        $this->jsonRequest('POST', '/api/game/player/traits/generate', [
            'description' => '',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function setUpMockGeminiClient(): void
    {
        $mockClient = new class implements GeminiClient {
            public function request(AiRequest $aiRequest): AiResponse
            {
                return new AiResponse(
                    '{"traits": {"leadership": 0.85, "empathy": 0.6}, "summary": "Test summary."}',
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

    private function setUpMockGeminiClientForSummary(): void
    {
        $mockClient = new class implements GeminiClient {
            public function request(AiRequest $aiRequest): AiResponse
            {
                return new AiResponse(
                    '{"summary": "Summary from traits."}',
                    new TokenUsage(50, 25, 75),
                    150,
                    'gemini-2.5-flash',
                    '{"candidates": []}',
                    'STOP',
                );
            }
        };

        self::getContainer()->set(GeminiClient::class, $mockClient);
    }
}
