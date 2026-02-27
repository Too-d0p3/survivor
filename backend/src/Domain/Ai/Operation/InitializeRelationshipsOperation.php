<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiResponseSchema;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Result\InitializeRelationshipsResult;
use App\Domain\Ai\Result\RelationshipValues;
use InvalidArgumentException;
use JsonException;

/**
 * @implements AiOperation<InitializeRelationshipsResult>
 */
final readonly class InitializeRelationshipsOperation implements AiOperation
{
    /** @var array<int, PlayerRelationshipInput> */
    private array $players;

    /**
     * @param array<int, PlayerRelationshipInput> $players
     */
    public function __construct(array $players)
    {
        $this->players = $this->validatePlayers($players);
    }

    public function getActionName(): string
    {
        return 'initializeRelationships';
    }

    public function getTemplateName(): string
    {
        return 'initialize_relationships';
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
                'relationships' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'source_index' => [
                                'type' => 'integer',
                                'description' => '1-based index of the player who holds this perception',
                            ],
                            'target_index' => [
                                'type' => 'integer',
                                'description' => '1-based index of the player being perceived',
                            ],
                            'trust' => ['type' => 'integer', 'description' => 'Trust level 0-100, 50 = neutral'],
                            'affinity' => ['type' => 'integer', 'description' => 'Affinity level 0-100, 50 = neutral'],
                            'respect' => ['type' => 'integer', 'description' => 'Respect level 0-100, 50 = neutral'],
                            'threat' => ['type' => 'integer', 'description' => 'Threat perception 0-100, 50 = neutral'],
                        ],
                        'required' => ['source_index', 'target_index', 'trust', 'affinity', 'respect', 'threat'],
                    ],
                ],
            ],
            ['relationships'],
        );
    }

    /** @phpstan-ignore return.unusedType (interface requires ?float) */
    public function getTemperature(): ?float
    {
        return 0.9;
    }

    /**
     * @return InitializeRelationshipsResult
     */
    public function parse(string $content): mixed
    {
        $actionName = $this->getActionName();
        $playerCount = count($this->players);
        $expectedCount = $playerCount * ($playerCount - 1);

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

        if (!isset($data['relationships'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Missing "relationships" key in response',
            );
        }

        if (!is_array($data['relationships'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                '"relationships" value is not an array',
            );
        }

        if (count($data['relationships']) !== $expectedCount) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf(
                    'Expected %d relationships for %d players, got %d',
                    $expectedCount,
                    $playerCount,
                    count($data['relationships']),
                ),
            );
        }

        $seenPairs = [];
        $relationships = [];

        foreach ($data['relationships'] as $i => $item) {
            if (!is_array($item)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Relationship at index %d is not an object', $i),
                );
            }

            /** @var array<string, mixed> $item */
            $sourceIndex = $this->extractIndex($item, 'source_index', $i, $playerCount, $actionName, $content);
            $targetIndex = $this->extractIndex($item, 'target_index', $i, $playerCount, $actionName, $content);

            if ($sourceIndex === $targetIndex) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Self-relationship at index %d: source_index and target_index are both %d', $i, $sourceIndex),
                );
            }

            $pairKey = $sourceIndex . ':' . $targetIndex;

            if (in_array($pairKey, $seenPairs, true)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Duplicate pair %s at index %d', $pairKey, $i),
                );
            }

            $seenPairs[] = $pairKey;

            $trust = $this->extractDimensionValue($item, 'trust', $i, $actionName, $content);
            $affinity = $this->extractDimensionValue($item, 'affinity', $i, $actionName, $content);
            $respect = $this->extractDimensionValue($item, 'respect', $i, $actionName, $content);
            $threat = $this->extractDimensionValue($item, 'threat', $i, $actionName, $content);

            $relationships[] = new RelationshipValues(
                $sourceIndex,
                $targetIndex,
                $trust,
                $affinity,
                $respect,
                $threat,
            );
        }

        return new InitializeRelationshipsResult($relationships);
    }

    /**
     * @param array<int, PlayerRelationshipInput> $players
     * @return array<int, PlayerRelationshipInput>
     */
    private function validatePlayers(array $players): array
    {
        if (count($players) < 2) {
            throw new InvalidArgumentException('At least 2 players are required');
        }

        foreach ($players as $index => $player) {
            if ($player->getName() === '') {
                throw new InvalidArgumentException(sprintf('Player at index %d has an empty name', $index));
            }

            if ($player->getDescription() === '') {
                throw new InvalidArgumentException(sprintf('Player at index %d has an empty description', $index));
            }

            if ($player->getTraitStrengths() === []) {
                throw new InvalidArgumentException(sprintf('Player at index %d has no trait strengths', $index));
            }
        }

        return $players;
    }

    private function formatMessage(): string
    {
        $parts = [];

        foreach (array_values($this->players) as $index => $player) {
            $playerNumber = $index + 1;
            $lines = [
                sprintf('Hráč %d: %s', $playerNumber, $player->getName()),
                sprintf('Popis: %s', $player->getDescription()),
                'Vlastnosti:',
            ];

            foreach ($player->getTraitStrengths() as $key => $strength) {
                $lines[] = sprintf('- %s: %s', $key, $strength);
            }

            $parts[] = implode("\n", $lines);
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractIndex(
        array $item,
        string $field,
        int $itemIndex,
        int $playerCount,
        string $actionName,
        string $content,
    ): int {
        if (!isset($item[$field]) || !is_int($item[$field])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('Missing or non-integer "%s" at relationship index %d', $field, $itemIndex),
            );
        }

        $value = $item[$field];

        if ($value < 1 || $value > $playerCount) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('%s value %d is out of range [1, %d] at relationship index %d', $field, $value, $playerCount, $itemIndex),
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractDimensionValue(
        array $item,
        string $field,
        int $itemIndex,
        string $actionName,
        string $content,
    ): int {
        if (!isset($item[$field])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('Missing "%s" at relationship index %d', $field, $itemIndex),
            );
        }

        if (!is_int($item[$field])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('"%s" at relationship index %d is not an integer', $field, $itemIndex),
            );
        }

        $value = $item[$field];

        if ($value < 0 || $value > 100) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('"%s" value %d at relationship index %d is out of range [0, 100]', $field, $value, $itemIndex),
            );
        }

        return $value;
    }
}
