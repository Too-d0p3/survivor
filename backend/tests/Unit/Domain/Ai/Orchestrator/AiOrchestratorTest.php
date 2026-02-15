<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Orchestrator;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Client\GeminiConfiguration;
use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Dto\AiResponseSchema;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Log\AiLogStatus;
use App\Domain\Ai\Operation\AiOperation;
use App\Domain\Ai\Orchestrator\AiOrchestrator;
use App\Domain\Ai\Prompt\PromptLoader;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AiOrchestratorTest extends TestCase
{
    private DateTimeImmutable $now;

    public function testExecuteSuccessReturnsSuccessResult(): void
    {
        $geminiClient = $this->createSuccessGeminiClient('{"value": "test"}');
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createOperation('parsed-value');

        $result = $orchestrator->execute($operation, $this->now);

        self::assertTrue($result->isSuccess());
        self::assertSame('parsed-value', $result->getResult());
    }

    public function testExecuteSuccessCreatesSuccessLog(): void
    {
        $geminiClient = $this->createSuccessGeminiClient('{"value": "test"}');
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createOperation('parsed-value');

        $result = $orchestrator->execute($operation, $this->now);
        $log = $result->getLog();

        self::assertSame(AiLogStatus::Success, $log->getStatus());
        self::assertSame('test-action', $log->getActionName());
        self::assertSame('test-model', $log->getModelName());
        self::assertSame('Loaded prompt', $log->getSystemPrompt());
        self::assertSame('user input', $log->getUserPrompt());
    }

    public function testExecuteSuccessLogUsesProvidedTimestamp(): void
    {
        $geminiClient = $this->createSuccessGeminiClient('{"value": "test"}');
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createOperation('parsed-value');

        $result = $orchestrator->execute($operation, $this->now);
        $log = $result->getLog();

        self::assertSame($this->now, $log->getCreatedAt());
    }

    public function testExecuteClientErrorReturnsFailureResult(): void
    {
        $geminiClient = $this->createErrorGeminiClient();
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createOperation('ignored');

        $result = $orchestrator->execute($operation, $this->now);

        self::assertFalse($result->isSuccess());
        self::assertInstanceOf(AiRequestFailedException::class, $result->getError());
    }

    public function testExecuteClientErrorCreatesErrorLog(): void
    {
        $geminiClient = $this->createErrorGeminiClient();
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createOperation('ignored');

        $result = $orchestrator->execute($operation, $this->now);
        $log = $result->getLog();

        self::assertSame(AiLogStatus::Error, $log->getStatus());
        self::assertSame('test-action', $log->getActionName());
    }

    public function testExecuteParseErrorReturnsFailureWithSuccessLog(): void
    {
        $geminiClient = $this->createSuccessGeminiClient('{"value": "test"}');
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createParseFailingOperation();

        $result = $orchestrator->execute($operation, $this->now);

        self::assertFalse($result->isSuccess());
        self::assertInstanceOf(AiResponseParsingFailedException::class, $result->getError());

        $log = $result->getLog();
        self::assertSame(AiLogStatus::Success, $log->getStatus());
    }

    public function testExecuteUsesOperationTemperatureWhenProvided(): void
    {
        $geminiClient = $this->createSuccessGeminiClient('{"value": "test"}');
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createOperationWithTemperature(0.9, 'parsed');

        $result = $orchestrator->execute($operation, $this->now);
        $log = $result->getLog();

        self::assertSame(0.9, $log->getTemperature());
    }

    public function testExecuteUsesDefaultTemperatureWhenOperationReturnsNull(): void
    {
        $geminiClient = $this->createSuccessGeminiClient('{"value": "test"}');
        $promptLoader = $this->createPromptLoader('Loaded prompt');
        $configuration = $this->createConfiguration();

        $orchestrator = new AiOrchestrator($geminiClient, $promptLoader, $configuration);

        $operation = $this->createOperation('parsed');

        $result = $orchestrator->execute($operation, $this->now);
        $log = $result->getLog();

        self::assertSame(0.7, $log->getTemperature());
    }

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-15 12:00:00');
    }

    /**
     * @return AiOperation<string>
     */
    private function createOperation(string $parseResult): AiOperation
    {
        return new readonly class ($parseResult) implements AiOperation {
            private string $parseResult;

            public function __construct(string $parseResult)
            {
                $this->parseResult = $parseResult;
            }

            public function getActionName(): string
            {
                return 'test-action';
            }

            public function getTemplateName(): string
            {
                return 'test_template';
            }

            /** @return array<string, string> */
            public function getTemplateVariables(): array
            {
                return [];
            }

            /** @return array<int, AiMessage> */
            public function getMessages(): array
            {
                return [AiMessage::user('user input')];
            }

            public function getResponseSchema(): AiResponseSchema
            {
                return new AiResponseSchema('object', ['value' => ['type' => 'string']], ['value']);
            }

            public function getTemperature(): ?float
            {
                return null;
            }

            public function parse(string $content): mixed
            {
                return $this->parseResult;
            }
        };
    }

    /**
     * @return AiOperation<string>
     */
    private function createOperationWithTemperature(float $temperature, string $parseResult): AiOperation
    {
        return new readonly class ($temperature, $parseResult) implements AiOperation {
            private float $temperature;

            private string $parseResult;

            public function __construct(float $temperature, string $parseResult)
            {
                $this->temperature = $temperature;
                $this->parseResult = $parseResult;
            }

            public function getActionName(): string
            {
                return 'test-action';
            }

            public function getTemplateName(): string
            {
                return 'test_template';
            }

            /** @return array<string, string> */
            public function getTemplateVariables(): array
            {
                return [];
            }

            /** @return array<int, AiMessage> */
            public function getMessages(): array
            {
                return [AiMessage::user('user input')];
            }

            public function getResponseSchema(): AiResponseSchema
            {
                return new AiResponseSchema('object', ['value' => ['type' => 'string']], ['value']);
            }

            public function getTemperature(): float
            {
                return $this->temperature;
            }

            public function parse(string $content): mixed
            {
                return $this->parseResult;
            }
        };
    }

    /**
     * @return AiOperation<never>
     */
    private function createParseFailingOperation(): AiOperation
    {
        return new class implements AiOperation {
            public function getActionName(): string
            {
                return 'test-action';
            }

            public function getTemplateName(): string
            {
                return 'test_template';
            }

            /** @return array<string, string> */
            public function getTemplateVariables(): array
            {
                return [];
            }

            /** @return array<int, AiMessage> */
            public function getMessages(): array
            {
                return [AiMessage::user('user input')];
            }

            public function getResponseSchema(): AiResponseSchema
            {
                return new AiResponseSchema('object', ['value' => ['type' => 'string']], ['value']);
            }

            public function getTemperature(): ?float
            {
                return null;
            }

            public function parse(string $content): mixed
            {
                throw new AiResponseParsingFailedException('test-action', $content, 'Parse failed');
            }
        };
    }

    private function createSuccessGeminiClient(string $responseContent): GeminiClient
    {
        return new readonly class ($responseContent) implements GeminiClient {
            private string $responseContent;

            public function __construct(string $responseContent)
            {
                $this->responseContent = $responseContent;
            }

            public function request(AiRequest $aiRequest): AiResponse
            {
                return new AiResponse(
                    $this->responseContent,
                    new TokenUsage(100, 50, 150),
                    200,
                    'test-model-version',
                    '{"candidates": []}',
                    'STOP',
                );
            }
        };
    }

    private function createErrorGeminiClient(): GeminiClient
    {
        return new class implements GeminiClient {
            public function request(AiRequest $aiRequest): AiResponse
            {
                throw new AiRequestFailedException(
                    $aiRequest->getActionName(),
                    500,
                    'Request failed',
                );
            }
        };
    }

    private function createPromptLoader(string $content): PromptLoader
    {
        $tempDir = sys_get_temp_dir() . '/ai_orchestrator_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/test_template.md', $content);

        return new PromptLoader($tempDir);
    }

    private function createConfiguration(): GeminiConfiguration
    {
        return new GeminiConfiguration(
            'test-api-key',
            'test-model',
            'https://test.api.com',
            0.7,
        );
    }
}
