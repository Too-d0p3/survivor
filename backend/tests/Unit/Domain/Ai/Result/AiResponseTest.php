<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use PHPUnit\Framework\TestCase;

final class AiResponseTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $tokenUsage = new TokenUsage(100, 50, 150);
        $rawJson = '{"candidates": [{"content": "test"}]}';

        $response = new AiResponse(
            'Test content',
            $tokenUsage,
            1234,
            'gemini-1.5-pro-002',
            $rawJson,
            'STOP',
        );

        self::assertSame('Test content', $response->getContent());
        self::assertSame($tokenUsage, $response->getTokenUsage());
        self::assertSame(1234, $response->getDurationMs());
        self::assertSame('gemini-1.5-pro-002', $response->getModelVersion());
        self::assertSame($rawJson, $response->getRawResponseJson());
        self::assertSame('STOP', $response->getFinishReason());
    }

    public function testConstructorWithEmptyContent(): void
    {
        $tokenUsage = new TokenUsage(10, 0, 10);

        $response = new AiResponse(
            '',
            $tokenUsage,
            100,
            'gemini-2.0-flash',
            '{}',
            'STOP',
        );

        self::assertSame('', $response->getContent());
    }

    public function testConstructorWithMultilineContent(): void
    {
        $tokenUsage = new TokenUsage(200, 100, 300);
        $content = "Line 1\nLine 2\nLine 3";

        $response = new AiResponse(
            $content,
            $tokenUsage,
            5000,
            'gemini-1.5-pro',
            '{"test": "data"}',
            'MAX_TOKENS',
        );

        self::assertSame($content, $response->getContent());
        self::assertSame('MAX_TOKENS', $response->getFinishReason());
    }
}
