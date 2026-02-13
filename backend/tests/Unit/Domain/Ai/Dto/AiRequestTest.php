<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Dto;

use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Dto\AiResponseSchema;
use PHPUnit\Framework\TestCase;

final class AiRequestTest extends TestCase
{
    public function testConstructorSetsAllPropertiesWithDefaults(): void
    {
        $messages = [
            AiMessage::user('Test message'),
        ];

        $request = new AiRequest(
            'generate_traits',
            'You are a trait analyzer',
            $messages,
        );

        self::assertSame('generate_traits', $request->getActionName());
        self::assertSame('You are a trait analyzer', $request->getSystemInstruction());
        self::assertSame($messages, $request->getMessages());
        self::assertNull($request->getTemperature());
        self::assertNull($request->getResponseSchema());
    }

    public function testConstructorSetsAllPropertiesWithTemperature(): void
    {
        $messages = [
            AiMessage::user('Test message'),
        ];

        $request = new AiRequest(
            'generate_traits',
            'You are a trait analyzer',
            $messages,
            0.8,
        );

        self::assertSame('generate_traits', $request->getActionName());
        self::assertSame('You are a trait analyzer', $request->getSystemInstruction());
        self::assertSame($messages, $request->getMessages());
        self::assertSame(0.8, $request->getTemperature());
        self::assertNull($request->getResponseSchema());
    }

    public function testConstructorSetsAllPropertiesWithResponseSchema(): void
    {
        $messages = [
            AiMessage::user('Test message'),
            AiMessage::model('Previous response'),
        ];
        $schema = new AiResponseSchema('object', ['field' => ['type' => 'string']], ['field']);

        $request = new AiRequest(
            'generate_summary',
            'You are a summarizer',
            $messages,
            0.5,
            $schema,
        );

        self::assertSame('generate_summary', $request->getActionName());
        self::assertSame('You are a summarizer', $request->getSystemInstruction());
        self::assertSame($messages, $request->getMessages());
        self::assertSame(0.5, $request->getTemperature());
        self::assertSame($schema, $request->getResponseSchema());
    }

    public function testConstructorWithEmptyMessages(): void
    {
        $request = new AiRequest(
            'test_action',
            'Test instruction',
            [],
        );

        self::assertSame('test_action', $request->getActionName());
        self::assertSame('Test instruction', $request->getSystemInstruction());
        self::assertSame([], $request->getMessages());
    }

    public function testToGeminiRequestBodyWithSchema(): void
    {
        $messages = [
            AiMessage::user('Test message'),
        ];
        $schema = new AiResponseSchema(
            'object',
            ['field' => ['type' => 'string']],
            ['field'],
        );

        $request = new AiRequest(
            'generate_traits',
            'You are a trait analyzer',
            $messages,
            null,
            $schema,
        );

        $result = $request->toGeminiRequestBody(0.7);

        self::assertArrayHasKey('systemInstruction', $result);
        self::assertArrayHasKey('contents', $result);
        self::assertArrayHasKey('generationConfig', $result);

        self::assertIsArray($result['systemInstruction']);
        self::assertIsArray($result['systemInstruction']['parts']);
        self::assertIsArray($result['systemInstruction']['parts'][0]);
        self::assertSame('You are a trait analyzer', $result['systemInstruction']['parts'][0]['text']);

        self::assertIsArray($result['contents']);
        self::assertCount(1, $result['contents']);
        self::assertIsArray($result['contents'][0]);
        self::assertSame('user', $result['contents'][0]['role']);
        self::assertIsArray($result['contents'][0]['parts']);
        self::assertIsArray($result['contents'][0]['parts'][0]);
        self::assertSame('Test message', $result['contents'][0]['parts'][0]['text']);

        self::assertIsArray($result['generationConfig']);
        self::assertSame(0.7, $result['generationConfig']['temperature']);
        self::assertSame('application/json', $result['generationConfig']['responseMimeType']);
        self::assertArrayHasKey('responseSchema', $result['generationConfig']);
    }

    public function testToGeminiRequestBodyWithoutSchema(): void
    {
        $messages = [
            AiMessage::user('Test message'),
        ];

        $request = new AiRequest(
            'test_action',
            'System prompt',
            $messages,
        );

        $result = $request->toGeminiRequestBody(0.5);

        self::assertArrayHasKey('generationConfig', $result);
        self::assertIsArray($result['generationConfig']);
        self::assertSame(0.5, $result['generationConfig']['temperature']);
        self::assertArrayNotHasKey('responseMimeType', $result['generationConfig']);
        self::assertArrayNotHasKey('responseSchema', $result['generationConfig']);
    }

    public function testToGeminiRequestBodyUsesDefaultTemperature(): void
    {
        $messages = [
            AiMessage::user('Test message'),
        ];

        $request = new AiRequest(
            'test_action',
            'System prompt',
            $messages,
            null,
        );

        $result = $request->toGeminiRequestBody(0.7);

        self::assertArrayHasKey('generationConfig', $result);
        self::assertIsArray($result['generationConfig']);
        self::assertSame(0.7, $result['generationConfig']['temperature']);
    }

    public function testToGeminiRequestBodyUsesRequestTemperature(): void
    {
        $messages = [
            AiMessage::user('Test message'),
        ];

        $request = new AiRequest(
            'test_action',
            'System prompt',
            $messages,
            0.3,
        );

        $result = $request->toGeminiRequestBody(0.7);

        self::assertArrayHasKey('generationConfig', $result);
        self::assertIsArray($result['generationConfig']);
        self::assertSame(0.3, $result['generationConfig']['temperature']);
    }
}
