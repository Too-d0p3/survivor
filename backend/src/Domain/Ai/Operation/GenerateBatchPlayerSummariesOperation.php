<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiResponseSchema;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Result\GenerateBatchSummaryResult;
use InvalidArgumentException;
use JsonException;

/**
 * @implements AiOperation<GenerateBatchSummaryResult>
 */
final readonly class GenerateBatchPlayerSummariesOperation implements AiOperation
{
    private const int MAX_SUMMARY_LENGTH = 200;

    /** @var array<int, array<string, string>> */
    private array $playerTraitStrengths;

    /**
     * @param array<int, array<string, string>> $playerTraitStrengths
     */
    public function __construct(array $playerTraitStrengths)
    {
        $this->playerTraitStrengths = $this->validateAndFormat($playerTraitStrengths);
    }

    public function getActionName(): string
    {
        return 'generateBatchPlayerTraitsSummaryDescriptions';
    }

    public function getTemplateName(): string
    {
        return 'generate_batch_player_summaries';
    }

    /**
     * @return array<string, string>
     */
    public function getTemplateVariables(): array
    {
        return [];
    }

    /**
     * @return array<int, AiMessage>
     */
    public function getMessages(): array
    {
        return [AiMessage::user($this->formatMessage())];
    }

    public function getResponseSchema(): AiResponseSchema
    {
        return new AiResponseSchema(
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
    }

    public function getTemperature(): ?float
    {
        return null;
    }

    /**
     * @return GenerateBatchSummaryResult
     */
    public function parse(string $content): mixed
    {
        $actionName = $this->getActionName();
        $expectedCount = count($this->playerTraitStrengths);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Invalid JSON: ' . $exception->getMessage(),
                $exception,
            );
        }

        if (!is_array($data)) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Response is not a JSON object',
            );
        }

        if (!isset($data['summaries'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Missing "summaries" key in response',
            );
        }

        if (!is_array($data['summaries'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                '"summaries" value is not an array',
            );
        }

        if (count($data['summaries']) !== $expectedCount) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('Expected %d summaries, got %d', $expectedCount, count($data['summaries'])),
            );
        }

        $seenIndexes = [];
        $indexedSummaries = [];

        foreach ($data['summaries'] as $i => $item) {
            if (!is_array($item)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Summary at index %d is not an object', $i),
                );
            }

            if (!isset($item['player_index']) || !is_int($item['player_index'])) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Missing or invalid player_index at index %d', $i),
                );
            }

            $playerIndex = $item['player_index'];

            if ($playerIndex < 1 || $playerIndex > $expectedCount) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('player_index %d out of range', $playerIndex),
                );
            }

            if (in_array($playerIndex, $seenIndexes, true)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Duplicate player_index %d', $playerIndex),
                );
            }

            $seenIndexes[] = $playerIndex;

            if (!isset($item['summary'])) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Missing "summary" key at index %d', $i),
                );
            }

            if (!is_string($item['summary'])) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('"summary" value at index %d is not a string', $i),
                );
            }

            if (mb_strlen($item['summary']) > self::MAX_SUMMARY_LENGTH) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Summary at index %d exceeds %d character limit', $i, self::MAX_SUMMARY_LENGTH),
                );
            }

            $indexedSummaries[$playerIndex] = $item['summary'];
        }

        ksort($indexedSummaries);
        $orderedSummaries = array_values($indexedSummaries);

        return new GenerateBatchSummaryResult($orderedSummaries);
    }

    /**
     * @param array<int, array<string, string>> $playerTraitStrengths
     * @return array<int, array<string, string>>
     */
    private function validateAndFormat(array $playerTraitStrengths): array
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

    private function formatMessage(): string
    {
        $parts = [];

        foreach (array_values($this->playerTraitStrengths) as $index => $traits) {
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
