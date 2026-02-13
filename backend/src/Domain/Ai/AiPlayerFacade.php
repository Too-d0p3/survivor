<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Ai\Client\GeminiClient;
use App\Domain\Ai\Client\GeminiConfiguration;
use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Dto\AiResponseSchema;
use App\Domain\Ai\Exceptions\AiRateLimitExceededException;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Exceptions\AiResponseBlockedBySafetyException;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Prompt\PromptLoader;
use App\Domain\Ai\Result\GenerateSummaryResult;
use App\Domain\Ai\Result\GenerateTraitsResult;
use App\Domain\Ai\Service\AiResponseParser;
use App\Domain\TraitDef\TraitDef;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class AiPlayerFacade
{
    private readonly GeminiClient $geminiClient;

    private readonly EntityManagerInterface $entityManager;

    private readonly AiResponseParser $aiResponseParser;

    private readonly PromptLoader $promptLoader;

    private readonly GeminiConfiguration $configuration;

    public function __construct(
        GeminiClient $geminiClient,
        EntityManagerInterface $entityManager,
        AiResponseParser $aiResponseParser,
        PromptLoader $promptLoader,
        GeminiConfiguration $configuration,
    ) {
        $this->geminiClient = $geminiClient;
        $this->entityManager = $entityManager;
        $this->aiResponseParser = $aiResponseParser;
        $this->promptLoader = $promptLoader;
        $this->configuration = $configuration;
    }

    /**
     * @param array<int, TraitDef> $traits
     */
    public function generatePlayerTraitsFromDescription(string $description, array $traits): GenerateTraitsResult
    {
        $now = new DateTimeImmutable();

        $traitKeysString = implode(', ', array_map(fn(TraitDef $trait) => $trait->getKey(), $traits));
        $systemPrompt = $this->promptLoader->load('generate_player_traits', ['traitKeys' => $traitKeysString]);

        $traitProperties = [];
        foreach ($traits as $trait) {
            $traitProperties[$trait->getKey()] = ['type' => 'number'];
        }

        $responseSchema = new AiResponseSchema(
            'object',
            [
                'traits' => [
                    'type' => 'object',
                    'properties' => $traitProperties,
                ],
                'summary' => ['type' => 'string'],
            ],
            ['traits', 'summary'],
        );

        $aiRequest = new AiRequest(
            'generatePlayerTraitsFromDescription',
            $systemPrompt,
            [AiMessage::user($description)],
            null,
            $responseSchema,
        );

        $requestBody = $aiRequest->toGeminiRequestBody($this->configuration->getDefaultTemperature());
        $requestJson = json_encode($requestBody, JSON_THROW_ON_ERROR);

        $aiLog = new AiLog(
            $this->configuration->getModel(),
            $now,
            'generatePlayerTraitsFromDescription',
            $systemPrompt,
            $description,
            $requestJson,
            $this->configuration->getDefaultTemperature(),
        );

        $this->entityManager->persist($aiLog);

        try {
            $aiResponse = $this->geminiClient->request($aiRequest);
            $aiLog->recordSuccess($aiResponse);
            $result = $this->aiResponseParser->parseGenerateTraitsResponse(
                $aiResponse->getContent(),
                $traits,
                'generatePlayerTraitsFromDescription',
            );

            $this->entityManager->flush();

            return $result;
        } catch (AiRequestFailedException | AiRateLimitExceededException | AiResponseBlockedBySafetyException | AiResponseParsingFailedException $exception) {
            $aiLog->recordError($exception->getMessage());
            $this->entityManager->flush();

            throw $exception;
        }
    }

    /**
     * @param array<string, string> $traitStrengths
     */
    public function generatePlayerTraitsSummaryDescription(array $traitStrengths): GenerateSummaryResult
    {
        $now = new DateTimeImmutable();

        $userContent = '';
        foreach ($traitStrengths as $key => $strength) {
            $userContent .= sprintf("%s: %s\n", $key, $strength);
        }
        $userContent = trim($userContent);

        $systemPrompt = $this->promptLoader->load('generate_player_summary');

        $responseSchema = new AiResponseSchema(
            'object',
            ['summary' => ['type' => 'string']],
            ['summary'],
        );

        $aiRequest = new AiRequest(
            'generatePlayerTraitsSummaryDescription',
            $systemPrompt,
            [AiMessage::user($userContent)],
            null,
            $responseSchema,
        );

        $requestBody = $aiRequest->toGeminiRequestBody($this->configuration->getDefaultTemperature());
        $requestJson = json_encode($requestBody, JSON_THROW_ON_ERROR);

        $aiLog = new AiLog(
            $this->configuration->getModel(),
            $now,
            'generatePlayerTraitsSummaryDescription',
            $systemPrompt,
            $userContent,
            $requestJson,
            $this->configuration->getDefaultTemperature(),
        );

        $this->entityManager->persist($aiLog);

        try {
            $aiResponse = $this->geminiClient->request($aiRequest);
            $aiLog->recordSuccess($aiResponse);
            $result = $this->aiResponseParser->parseGenerateSummaryResponse(
                $aiResponse->getContent(),
                'generatePlayerTraitsSummaryDescription',
            );

            $this->entityManager->flush();

            return $result;
        } catch (AiRequestFailedException | AiRateLimitExceededException | AiResponseBlockedBySafetyException | AiResponseParsingFailedException $exception) {
            $aiLog->recordError($exception->getMessage());
            $this->entityManager->flush();

            throw $exception;
        }
    }
}
