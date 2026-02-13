<?php

declare(strict_types=1);

namespace App\Domain\Ai\Client;

use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Exceptions\AiRateLimitExceededException;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Exceptions\AiResponseBlockedBySafetyException;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpGeminiClient implements GeminiClient
{
    private readonly HttpClientInterface $httpClient;

    private readonly GeminiConfiguration $configuration;

    public function __construct(
        HttpClientInterface $httpClient,
        GeminiConfiguration $configuration,
    ) {
        $this->httpClient = $httpClient;
        $this->configuration = $configuration;
    }

    public function request(AiRequest $aiRequest): AiResponse
    {
        $requestBody = $aiRequest->toGeminiRequestBody($this->configuration->getDefaultTemperature());
        $startTime = hrtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->configuration->getEndpointUrl(), [
                'query' => [
                    'key' => $this->configuration->getApiKey(),
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                throw new AiRateLimitExceededException($aiRequest->getActionName());
            }

            if ($statusCode >= 400) {
                throw new AiRequestFailedException(
                    $aiRequest->getActionName(),
                    $statusCode,
                    $response->getContent(false),
                );
            }

            $durationMs = (int) round((hrtime(true) - $startTime) / 1_000_000);

            /** @var array<string, mixed> $responseData */
            $responseData = $response->toArray();

            return $this->parseResponse($responseData, $aiRequest->getActionName(), $durationMs);
        } catch (TransportExceptionInterface $exception) {
            throw new AiRequestFailedException(
                $aiRequest->getActionName(),
                null,
                $exception->getMessage(),
                $exception,
            );
        }
    }

    /**
     * @param array<string, mixed> $responseData
     */
    private function parseResponse(array $responseData, string $actionName, int $durationMs): AiResponse
    {
        if (!isset($responseData['candidates']) || !is_array($responseData['candidates']) || count($responseData['candidates']) === 0) {
            throw new AiResponseParsingFailedException(
                $actionName,
                json_encode($responseData, JSON_THROW_ON_ERROR),
                'Response does not contain candidates array',
            );
        }

        $candidate = $responseData['candidates'][0];

        if (!is_array($candidate)) {
            throw new AiResponseParsingFailedException(
                $actionName,
                json_encode($responseData, JSON_THROW_ON_ERROR),
                'Candidate is not an array',
            );
        }

        if (!isset($candidate['finishReason']) || !is_string($candidate['finishReason'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                json_encode($responseData, JSON_THROW_ON_ERROR),
                'Candidate does not contain finishReason',
            );
        }

        $finishReason = $candidate['finishReason'];

        if ($finishReason === 'SAFETY') {
            throw new AiResponseBlockedBySafetyException($actionName);
        }

        if (!isset($candidate['content']) || !is_array($candidate['content'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                json_encode($responseData, JSON_THROW_ON_ERROR),
                'Candidate does not contain content array',
            );
        }

        $content = $candidate['content'];

        if (!isset($content['parts']) || !is_array($content['parts']) || count($content['parts']) === 0) {
            throw new AiResponseParsingFailedException(
                $actionName,
                json_encode($responseData, JSON_THROW_ON_ERROR),
                'Content does not contain parts array',
            );
        }

        $parts = $content['parts'];
        $firstPart = $parts[0];

        if (!is_array($firstPart) || !isset($firstPart['text']) || !is_string($firstPart['text'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                json_encode($responseData, JSON_THROW_ON_ERROR),
                'First part does not contain text string',
            );
        }

        $textContent = $firstPart['text'];

        if (!isset($responseData['usageMetadata']) || !is_array($responseData['usageMetadata'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                json_encode($responseData, JSON_THROW_ON_ERROR),
                'Response does not contain usageMetadata',
            );
        }

        $usageMetadata = $responseData['usageMetadata'];

        $promptTokenCount = isset($usageMetadata['promptTokenCount']) && is_int($usageMetadata['promptTokenCount'])
            ? $usageMetadata['promptTokenCount']
            : 0;

        $candidatesTokenCount = isset($usageMetadata['candidatesTokenCount']) && is_int($usageMetadata['candidatesTokenCount'])
            ? $usageMetadata['candidatesTokenCount']
            : 0;

        $totalTokenCount = isset($usageMetadata['totalTokenCount']) && is_int($usageMetadata['totalTokenCount'])
            ? $usageMetadata['totalTokenCount']
            : 0;

        $tokenUsage = new TokenUsage(
            $promptTokenCount,
            $candidatesTokenCount,
            $totalTokenCount,
        );

        $modelVersion = isset($responseData['modelVersion']) && is_string($responseData['modelVersion'])
            ? $responseData['modelVersion']
            : '';

        $rawResponseJson = json_encode($responseData, JSON_THROW_ON_ERROR);

        return new AiResponse(
            $textContent,
            $tokenUsage,
            $durationMs,
            $modelVersion,
            $rawResponseJson,
            $finishReason,
        );
    }
}
