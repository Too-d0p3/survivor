<?php

declare(strict_types=1);

namespace App\Domain\Ai\Service;

use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Result\GenerateSummaryResult;
use App\Domain\Ai\Result\GenerateTraitsResult;
use App\Domain\TraitDef\TraitDef;
use JsonException;

final class AiResponseParser
{
    /**
     * @param array<int, TraitDef> $availableTraits
     */
    public function parseGenerateTraitsResponse(string $content, array $availableTraits, string $actionName): GenerateTraitsResult
    {
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
        foreach ($availableTraits as $trait) {
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

    public function parseGenerateSummaryResponse(string $content, string $actionName): GenerateSummaryResult
    {
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

        return new GenerateSummaryResult($data['summary']);
    }
}
