<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Log;

use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Log\AiLogStatus;
use App\Domain\Ai\Result\AiResponse;
use App\Domain\Ai\Result\TokenUsage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AiLogTest extends TestCase
{
    public function testConstructorSetsFieldsCorrectly(): void
    {
        $modelName = 'gemini-2.5-flash';
        $createdAt = new DateTimeImmutable('2024-01-01 12:00:00');
        $actionName = 'generate_traits';
        $systemPrompt = 'You are a trait generator.';
        $userPrompt = 'Generate traits for Alice.';
        $requestJson = '{"model": "gemini-2.5-flash"}';
        $temperature = 0.7;

        $aiLog = new AiLog(
            $modelName,
            $createdAt,
            $actionName,
            $systemPrompt,
            $userPrompt,
            $requestJson,
            $temperature,
        );

        self::assertNotEmpty($aiLog->getId()->toRfc4122());
        self::assertSame($modelName, $aiLog->getModelName());
        self::assertSame($createdAt, $aiLog->getCreatedAt());
        self::assertSame($actionName, $aiLog->getActionName());
        self::assertSame($systemPrompt, $aiLog->getSystemPrompt());
        self::assertSame($userPrompt, $aiLog->getUserPrompt());
        self::assertSame($requestJson, $aiLog->getRequestJson());
        self::assertSame($temperature, $aiLog->getTemperature());
        self::assertSame(AiLogStatus::Pending, $aiLog->getStatus());
        self::assertNull($aiLog->getResponseJson());
        self::assertNull($aiLog->getReturnContent());
        self::assertNull($aiLog->getPromptTokenCount());
        self::assertNull($aiLog->getCandidatesTokenCount());
        self::assertNull($aiLog->getTotalTokenCount());
        self::assertNull($aiLog->getDurationMs());
        self::assertNull($aiLog->getModelVersion());
        self::assertNull($aiLog->getFinishReason());
        self::assertNull($aiLog->getErrorMessage());
    }

    public function testRecordSuccessUpdatesAllFields(): void
    {
        $aiLog = new AiLog(
            'gemini-2.5-flash',
            new DateTimeImmutable(),
            'generate_traits',
            'System prompt',
            'User prompt',
            '{"request": "data"}',
            0.7,
        );

        $tokenUsage = new TokenUsage(100, 50, 150);
        $aiResponse = new AiResponse(
            '{"traits": {"leadership": 0.8}}',
            $tokenUsage,
            250,
            'gemini-2.5-flash-001',
            '{"candidates": [{"content": {"parts": [{"text": "..."}]}}]}',
            'STOP',
        );

        $aiLog->recordSuccess($aiResponse);

        self::assertSame(AiLogStatus::Success, $aiLog->getStatus());
        self::assertSame('{"candidates": [{"content": {"parts": [{"text": "..."}]}}]}', $aiLog->getResponseJson());
        self::assertSame('{"traits": {"leadership": 0.8}}', $aiLog->getReturnContent());
        self::assertSame(100, $aiLog->getPromptTokenCount());
        self::assertSame(50, $aiLog->getCandidatesTokenCount());
        self::assertSame(150, $aiLog->getTotalTokenCount());
        self::assertSame(250, $aiLog->getDurationMs());
        self::assertSame('gemini-2.5-flash-001', $aiLog->getModelVersion());
        self::assertSame('STOP', $aiLog->getFinishReason());
    }

    public function testRecordErrorUpdatesStatusAndMessage(): void
    {
        $aiLog = new AiLog(
            'gemini-2.5-flash',
            new DateTimeImmutable(),
            'generate_traits',
            'System prompt',
            'User prompt',
            '{"request": "data"}',
            0.7,
        );

        $errorMessage = 'API request failed: 500 Internal Server Error';
        $durationMs = 150;

        $aiLog->recordError($errorMessage, $durationMs);

        self::assertSame(AiLogStatus::Error, $aiLog->getStatus());
        self::assertSame($errorMessage, $aiLog->getErrorMessage());
        self::assertSame($durationMs, $aiLog->getDurationMs());
        self::assertNull($aiLog->getResponseJson());
        self::assertNull($aiLog->getReturnContent());
    }

    public function testRecordErrorWithoutDurationLeavesItNull(): void
    {
        $aiLog = new AiLog(
            'gemini-2.5-flash',
            new DateTimeImmutable(),
            'generate_traits',
            'System prompt',
            'User prompt',
            '{"request": "data"}',
            0.7,
        );

        $errorMessage = 'Connection timeout';

        $aiLog->recordError($errorMessage);

        self::assertSame(AiLogStatus::Error, $aiLog->getStatus());
        self::assertSame($errorMessage, $aiLog->getErrorMessage());
        self::assertNull($aiLog->getDurationMs());
    }
}
