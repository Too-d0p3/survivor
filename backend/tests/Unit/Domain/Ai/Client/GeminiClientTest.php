<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Client;

use App\Domain\Ai\Client\GeminiConfiguration;
use App\Domain\Ai\Client\HttpGeminiClient;
use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Dto\AiResponseSchema;
use App\Domain\Ai\Exceptions\AiRateLimitExceededException;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Exceptions\AiResponseBlockedBySafetyException;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiClientTest extends TestCase
{
    private GeminiConfiguration $configuration;

    public function testRequestSuccessfulResponseReturnsAiResponse(): void
    {
        $responseBody = json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '{"traits": {"leadership": 0.8}, "summary": "Test."}'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 100,
                'candidatesTokenCount' => 50,
                'totalTokenCount' => 150,
            ],
            'modelVersion' => 'gemini-2.5-flash',
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'You are a helpful assistant',
            [AiMessage::user('Generate traits')],
        );

        $result = $client->request($aiRequest);

        self::assertSame('{"traits": {"leadership": 0.8}, "summary": "Test."}', $result->getContent());
        self::assertSame(100, $result->getTokenUsage()->getPromptTokenCount());
        self::assertSame(50, $result->getTokenUsage()->getCandidatesTokenCount());
        self::assertSame(150, $result->getTokenUsage()->getTotalTokenCount());
        self::assertSame('gemini-2.5-flash', $result->getModelVersion());
        self::assertSame('STOP', $result->getFinishReason());
        self::assertGreaterThanOrEqual(0, $result->getDurationMs());
    }

    public function testRequestWithStructuredOutputIncludesSchemaInBody(): void
    {
        $responseBody = json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '{"summary": "Test"}'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
                'totalTokenCount' => 15,
            ],
            'modelVersion' => 'gemini-2.5-flash',
        ], JSON_THROW_ON_ERROR);

        /** @var array<string, mixed>|null $capturedRequestBody */
        $capturedRequestBody = null;
        $mockResponse = new MockResponse(
            $responseBody,
            [
                'http_code' => 200,
            ],
        );

        $httpClient = new MockHttpClient(function ($method, $url, $options) use ($mockResponse, &$capturedRequestBody) {
            /** @var array<string, mixed> $options */
            $body = $options['body'] ?? '';
            $capturedRequestBody = json_decode(is_string($body) ? $body : '', true, 512, JSON_THROW_ON_ERROR);

            return $mockResponse;
        });

        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $schema = new AiResponseSchema(
            'object',
            [
                'summary' => ['type' => 'string'],
            ],
            ['summary'],
        );

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Generate summary')],
            null,
            $schema,
        );

        $client->request($aiRequest);

        self::assertIsArray($capturedRequestBody);
        self::assertArrayHasKey('generationConfig', $capturedRequestBody);
        /** @var array<string, mixed> $generationConfig */
        $generationConfig = $capturedRequestBody['generationConfig'];
        self::assertArrayHasKey('responseMimeType', $generationConfig);
        self::assertSame('application/json', $generationConfig['responseMimeType']);
        self::assertArrayHasKey('responseSchema', $generationConfig);
        /** @var array<string, mixed> $responseSchema */
        $responseSchema = $generationConfig['responseSchema'];
        self::assertSame('object', $responseSchema['type']);
    }

    public function testRequestWithoutSchemaOmitsSchemaFromBody(): void
    {
        $responseBody = json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Plain text response'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
                'totalTokenCount' => 15,
            ],
        ], JSON_THROW_ON_ERROR);

        /** @var array<string, mixed>|null $capturedRequestBody */
        $capturedRequestBody = null;
        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);

        $httpClient = new MockHttpClient(function ($method, $url, $options) use ($mockResponse, &$capturedRequestBody) {
            /** @var array<string, mixed> $options */
            $body = $options['body'] ?? '';
            $capturedRequestBody = json_decode(is_string($body) ? $body : '', true, 512, JSON_THROW_ON_ERROR);

            return $mockResponse;
        });

        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Plain query')],
        );

        $client->request($aiRequest);

        self::assertIsArray($capturedRequestBody);
        self::assertArrayHasKey('generationConfig', $capturedRequestBody);
        /** @var array<string, mixed> $generationConfig */
        $generationConfig = $capturedRequestBody['generationConfig'];
        self::assertArrayNotHasKey('responseMimeType', $generationConfig);
        self::assertArrayNotHasKey('responseSchema', $generationConfig);
    }

    public function testRequestWith429ThrowsRateLimitException(): void
    {
        $mockResponse = new MockResponse('Rate limit exceeded', ['http_code' => 429]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Test')],
        );

        $this->expectException(AiRateLimitExceededException::class);

        $client->request($aiRequest);
    }

    public function testRequestWith500ThrowsRequestFailedException(): void
    {
        $mockResponse = new MockResponse('Internal server error', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Test')],
        );

        $this->expectException(AiRequestFailedException::class);

        $client->request($aiRequest);
    }

    public function testRequestWithSafetyBlockThrowsBlockedException(): void
    {
        $responseBody = json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Blocked content'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'SAFETY',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 0,
                'totalTokenCount' => 10,
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Test')],
        );

        $this->expectException(AiResponseBlockedBySafetyException::class);

        $client->request($aiRequest);
    }

    public function testRequestWithEmptyCandidatesThrowsParsingException(): void
    {
        $responseBody = json_encode([
            'candidates' => [],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 0,
                'totalTokenCount' => 10,
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Test')],
        );

        $this->expectException(AiResponseParsingFailedException::class);

        $client->request($aiRequest);
    }

    public function testRequestMeasuresDuration(): void
    {
        $responseBody = json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Response'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
                'totalTokenCount' => 15,
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Test')],
        );

        $result = $client->request($aiRequest);

        self::assertGreaterThanOrEqual(0, $result->getDurationMs());
    }

    public function testRequestUsesCorrectEndpointAndApiKey(): void
    {
        $responseBody = json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Response'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
                'totalTokenCount' => 15,
            ],
        ], JSON_THROW_ON_ERROR);

        /** @var string|null $capturedUrl */
        $capturedUrl = null;
        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);

        $httpClient = new MockHttpClient(function ($method, $url, $options) use ($mockResponse, &$capturedUrl) {
            $capturedUrl = is_string($url) ? $url : '';

            return $mockResponse;
        });

        $client = new HttpGeminiClient($httpClient, $this->configuration);

        $aiRequest = new AiRequest(
            'test-action',
            'System instruction',
            [AiMessage::user('Test')],
        );

        $client->request($aiRequest);

        self::assertNotNull($capturedUrl);
        self::assertStringContainsString('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent', $capturedUrl);
        self::assertStringContainsString('key=test-api-key', $capturedUrl);
    }

    protected function setUp(): void
    {
        $this->configuration = new GeminiConfiguration(
            'test-api-key',
            'gemini-2.0-flash-exp',
            'https://generativelanguage.googleapis.com/v1beta',
            0.7,
        );
    }
}
