<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Ai;

use App\Domain\Ai\AiPlayerFacade;
use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Log\AiLogStatus;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Tests\Integration\AbstractIntegrationTestCase;

final class AiPlayerFacadeTest extends AbstractIntegrationTestCase
{
    public function testGeneratePlayerTraitsFromDescriptionReturnsGenerateTraitsResult(): void
    {
        $this->setUpMockGeminiClient();

        $entityManager = $this->getEntityManager();

        $leadership = new TraitDef('leadership', 'Leadership', 'Leading ability', TraitType::Social);
        $empathy = new TraitDef('empathy', 'Empathy', 'Understanding others', TraitType::Emotional);
        $entityManager->persist($leadership);
        $entityManager->persist($empathy);
        $entityManager->flush();

        $traits = [$leadership, $empathy];

        $aiPlayerFacade = $this->getService(AiPlayerFacade::class);
        $result = $aiPlayerFacade->generatePlayerTraitsFromDescription('A strong leader with empathy', $traits);

        self::assertSame(['leadership' => 0.85, 'empathy' => 0.6], $result->getTraitScores());
        self::assertSame('Test summary.', $result->getSummary());
    }

    public function testGeneratePlayerTraitsFromDescriptionPersistsAiLog(): void
    {
        $this->setUpMockGeminiClient();

        $entityManager = $this->getEntityManager();

        $leadership = new TraitDef('leadership', 'Leadership', 'Leading ability', TraitType::Social);
        $empathy = new TraitDef('empathy', 'Empathy', 'Understanding others', TraitType::Emotional);
        $entityManager->persist($leadership);
        $entityManager->persist($empathy);
        $entityManager->flush();

        $traits = [$leadership, $empathy];

        $aiPlayerFacade = $this->getService(AiPlayerFacade::class);
        $aiPlayerFacade->generatePlayerTraitsFromDescription('A strong leader', $traits);

        $aiLogRepository = $entityManager->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'generatePlayerTraitsFromDescription']);

        self::assertCount(1, $logs);

        $aiLog = $logs[0];
        self::assertSame(AiLogStatus::Success, $aiLog->getStatus());
        self::assertSame('gemini-2.5-flash', $aiLog->getModelName());
        self::assertSame('generatePlayerTraitsFromDescription', $aiLog->getActionName());
        self::assertSame(100, $aiLog->getPromptTokenCount());
        self::assertSame(50, $aiLog->getCandidatesTokenCount());
        self::assertSame(150, $aiLog->getTotalTokenCount());
    }

    public function testGeneratePlayerTraitsFromDescriptionOnErrorPersistsErrorLog(): void
    {
        $this->setUpMockGeminiClientWithError();

        $entityManager = $this->getEntityManager();

        $leadership = new TraitDef('leadership', 'Leadership', 'Leading ability', TraitType::Social);
        $empathy = new TraitDef('empathy', 'Empathy', 'Understanding others', TraitType::Emotional);
        $entityManager->persist($leadership);
        $entityManager->persist($empathy);
        $entityManager->flush();

        $traits = [$leadership, $empathy];

        $aiPlayerFacade = $this->getService(AiPlayerFacade::class);

        try {
            $aiPlayerFacade->generatePlayerTraitsFromDescription('A strong leader', $traits);
            self::fail('Expected AiRequestFailedException to be thrown');
        } catch (AiRequestFailedException $exception) {
            self::assertSame('generatePlayerTraitsFromDescription', $exception->getActionName());
        }

        $aiLogRepository = $entityManager->getRepository(AiLog::class);
        $logs = $aiLogRepository->findBy(['actionName' => 'generatePlayerTraitsFromDescription']);

        self::assertCount(1, $logs);

        $aiLog = $logs[0];
        self::assertSame(AiLogStatus::Error, $aiLog->getStatus());
        self::assertNotNull($aiLog->getErrorMessage());
        self::assertStringContainsString('Request failed', $aiLog->getErrorMessage());
    }

    public function testGeneratePlayerTraitsSummaryDescriptionReturnsResult(): void
    {
        $this->setUpMockGeminiClientForSummary();

        $traitStrengths = [
            'leadership' => '0.85',
            'empathy' => '0.6',
        ];

        $aiPlayerFacade = $this->getService(AiPlayerFacade::class);
        $result = $aiPlayerFacade->generatePlayerTraitsSummaryDescription($traitStrengths);

        self::assertSame('Summary from traits.', $result->getSummary());
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

    private function setUpMockGeminiClientWithError(): void
    {
        $mockClient = new class implements GeminiClient {
            public function request(AiRequest $aiRequest): AiResponse
            {
                throw new AiRequestFailedException(
                    $aiRequest->getActionName(),
                    500,
                    'Request failed',
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
