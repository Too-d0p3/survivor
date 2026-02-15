<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiResponseSchema;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Result\GenerateTraitsResult;
use App\Domain\TraitDef\TraitDef;
use JsonException;

/**
 * @implements AiOperation<GenerateTraitsResult>
 */
final readonly class GeneratePlayerTraitsOperation implements AiOperation
{
    private string $description;

    /** @var array<int, TraitDef> */
    private array $traits;

    /**
     * @param array<int, TraitDef> $traits
     */
    public function __construct(string $description, array $traits)
    {
        $this->description = $description;
        $this->traits = $traits;
    }

    public function getActionName(): string
    {
        return 'generatePlayerTraitsFromDescription';
    }

    public function getTemplateName(): string
    {
        return 'generate_player_traits';
    }

    /**
     * @return array<string, string>
     */
    public function getTemplateVariables(): array
    {
        $traitKeysString = implode(PHP_EOL, array_map(fn(TraitDef $trait) => '* ' . $trait->getKey(), $this->traits));

        return ['traitKeys' => $traitKeysString];
    }

    /**
     * @return array<int, AiMessage>
     */
    public function getMessages(): array
    {
        return [AiMessage::user($this->description)];
    }

    public function getResponseSchema(): AiResponseSchema
    {
        $traitProperties = [];
        foreach ($this->traits as $trait) {
            $traitProperties[$trait->getKey()] = ['type' => 'number'];
        }

        return new AiResponseSchema(
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
    }

    public function getTemperature(): ?float
    {
        return null;
    }

    /**
     * @return GenerateTraitsResult
     */
    public function parse(string $content): mixed
    {
        $actionName = $this->getActionName();

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

        if (!isset($data['traits'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Missing "traits" key in response',
            );
        }

        if (!is_array($data['traits'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                '"traits" value is not an object/array',
            );
        }

        if (!isset($data['summary'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Missing "summary" key in response',
            );
        }

        if (!is_string($data['summary'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                '"summary" value is not a string',
            );
        }

        $availableTraitKeys = [];
        foreach ($this->traits as $trait) {
            $availableTraitKeys[] = $trait->getKey();
        }

        $traitScores = [];
        foreach ($data['traits'] as $traitKey => $score) {
            if (!in_array($traitKey, $availableTraitKeys, true)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Unknown trait key "%s" in response', $traitKey),
                );
            }

            if (!is_float($score) && !is_int($score)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Trait score for "%s" is not a number', $traitKey),
                );
            }

            $scoreFloat = (float) $score;

            if ($scoreFloat < 0.0 || $scoreFloat > 1.0) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Trait score for "%s" is out of range [0.0, 1.0]: %f', $traitKey, $scoreFloat),
                );
            }

            $traitScores[$traitKey] = $scoreFloat;
        }

        $missingTraits = array_diff($availableTraitKeys, array_keys($traitScores));
        if ($missingTraits !== []) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('Missing trait keys in response: %s', implode(', ', $missingTraits)),
            );
        }

        return new GenerateTraitsResult($traitScores, $data['summary']);
    }
}
