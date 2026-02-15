<?php

declare(strict_types=1);

namespace App\Domain\Ai\Orchestrator;

use App\Domain\Ai\AiExecutor;
use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Client\GeminiConfiguration;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Operation\AiOperation;
use App\Domain\Ai\Prompt\PromptLoader;
use App\Domain\Ai\Result\AiCallResult;
use DateTimeImmutable;
use Throwable;

final readonly class AiOrchestrator implements AiExecutor
{
    private GeminiClient $geminiClient;

    private PromptLoader $promptLoader;

    private GeminiConfiguration $configuration;

    public function __construct(
        GeminiClient $geminiClient,
        PromptLoader $promptLoader,
        GeminiConfiguration $configuration,
    ) {
        $this->geminiClient = $geminiClient;
        $this->promptLoader = $promptLoader;
        $this->configuration = $configuration;
    }

    /**
     * @template T
     * @param AiOperation<T> $operation
     * @return AiCallResult<T>
     */
    public function execute(AiOperation $operation, DateTimeImmutable $now): AiCallResult
    {
        $systemPrompt = $this->promptLoader->load(
            $operation->getTemplateName(),
            $operation->getTemplateVariables(),
        );

        $aiRequest = new AiRequest(
            $operation->getActionName(),
            $systemPrompt,
            $operation->getMessages(),
            $operation->getTemperature(),
            $operation->getResponseSchema(),
        );

        $requestBody = $aiRequest->toGeminiRequestBody($this->configuration->getDefaultTemperature());
        $requestJson = json_encode($requestBody, JSON_THROW_ON_ERROR);

        $userContent = '';
        foreach ($operation->getMessages() as $message) {
            $userContent .= $message->getContent();
        }

        $aiLog = new AiLog(
            $this->configuration->getModel(),
            $now,
            $operation->getActionName(),
            $systemPrompt,
            $userContent,
            $requestJson,
            $operation->getTemperature() ?? $this->configuration->getDefaultTemperature(),
        );

        try {
            $aiResponse = $this->geminiClient->request($aiRequest);
            $aiLog->recordSuccess($aiResponse);
        } catch (Throwable $exception) {
            $aiLog->recordError($exception->getMessage());

            return AiCallResult::failure($aiLog, $exception);
        }

        try {
            $parsed = $operation->parse($aiResponse->getContent());

            return AiCallResult::success($parsed, $aiLog);
        } catch (Throwable $parseException) {
            return AiCallResult::failure($aiLog, $parseException);
        }
    }
}
