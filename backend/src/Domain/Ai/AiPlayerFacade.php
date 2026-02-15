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
use App\Domain\Ai\Result\GenerateBatchSummaryResult;
use App\Domain\Ai\Result\GenerateSummaryResult;
use App\Domain\Ai\Result\GenerateTraitsResult;
use App\Domain\Ai\Service\AiResponseParser;
use App\Domain\TraitDef\TraitDef;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

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
        $batchResult = $this->generateBatchPlayerTraitsSummaryDescriptions([$traitStrengths]);

        return new GenerateSummaryResult($batchResult->getSummaries()[0]);
    }

    /**
     * @param array<int, array<string, string>> $playerTraitStrengths
     */
    public function generateBatchPlayerTraitsSummaryDescriptions(array $playerTraitStrengths): GenerateBatchSummaryResult
    {
        $now = new DateTimeImmutable();

        $playerTraitStrengths = $this->validateAndFormatBatchTraitStrengths($playerTraitStrengths);

        $userContent = $this->formatBatchTraitStrengthsMessage($playerTraitStrengths);
        $systemPrompt = $this->promptLoader->load('generate_batch_player_summaries');

        $responseSchema = new AiResponseSchema(
            'object',
            [
                'summaries' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'player_index' => [
                                'type' => 'integer',
                                'description' => '1-based index matching input player order',
                            ],
                            'summary' => ['type' => 'string'],
                        ],
                        'required' => ['player_index', 'summary'],
                    ],
                ],
            ],
            ['summaries'],
        );

        $aiRequest = new AiRequest(
            'generateBatchPlayerTraitsSummaryDescriptions',
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
            'generateBatchPlayerTraitsSummaryDescriptions',
            $systemPrompt,
            $userContent,
            $requestJson,
            $this->configuration->getDefaultTemperature(),
        );

        $this->entityManager->persist($aiLog);

        try {
            $aiResponse = $this->geminiClient->request($aiRequest);
            $aiLog->recordSuccess($aiResponse);
            $result = $this->aiResponseParser->parseGenerateBatchSummaryResponse(
                $aiResponse->getContent(),
                count($playerTraitStrengths),
                'generateBatchPlayerTraitsSummaryDescriptions',
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
     * @param array<int, array<string, string>> $playerTraitStrengths
     * @return array<int, array<string, string>>
     */
    private function validateAndFormatBatchTraitStrengths(array $playerTraitStrengths): array
    {
        foreach ($playerTraitStrengths as $playerIndex => $traits) {
            foreach ($traits as $key => $value) {
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException(
                        sprintf('Trait strength value for "%s" is not numeric', $key),
                    );
                }

                $floatValue = (float) $value;

                if ($floatValue < 0.0 || $floatValue > 1.0) {
                    throw new InvalidArgumentException(
                        sprintf('Trait strength value for "%s" is out of range [0.0, 1.0]: %s', $key, $value),
                    );
                }

                $playerTraitStrengths[$playerIndex][$key] = number_format($floatValue, 2, '.', '');
            }
        }

        return $playerTraitStrengths;
    }

    /**
     * @param array<int, array<string, string>> $playerTraitStrengths
     */
    private function formatBatchTraitStrengthsMessage(array $playerTraitStrengths): string
    {
        $parts = [];

        foreach (array_values($playerTraitStrengths) as $index => $traits) {
            $playerNumber = $index + 1;
            $lines = [sprintf('Hráč %d:', $playerNumber)];

            foreach ($traits as $key => $strength) {
                $lines[] = sprintf('%s: %s', $key, $strength);
            }

            $parts[] = implode("\n", $lines);
        }

        return implode("\n\n", $parts);
    }
}
