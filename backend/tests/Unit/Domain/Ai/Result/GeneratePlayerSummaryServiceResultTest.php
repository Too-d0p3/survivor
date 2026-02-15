<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\GeneratePlayerSummaryServiceResult;
use App\Domain\Ai\Result\GenerateSummaryResult;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeneratePlayerSummaryServiceResultTest extends TestCase
{
    public function testSuccessIsSuccess(): void
    {
        $result = GeneratePlayerSummaryServiceResult::success(
            new GenerateSummaryResult('A strong leader'),
            [$this->createAiLog()],
        );

        self::assertTrue($result->isSuccess());
    }

    public function testSuccessGetResult(): void
    {
        $summaryResult = new GenerateSummaryResult('A strong leader');
        $result = GeneratePlayerSummaryServiceResult::success($summaryResult, [$this->createAiLog()]);

        self::assertSame($summaryResult, $result->getResult());
    }

    public function testSuccessGetLogs(): void
    {
        $log = $this->createAiLog();
        $result = GeneratePlayerSummaryServiceResult::success(
            new GenerateSummaryResult('A strong leader'),
            [$log],
        );

        self::assertCount(1, $result->getLogs());
        self::assertSame($log, $result->getLogs()[0]);
    }

    public function testSuccessGetErrorThrowsLogicException(): void
    {
        $result = GeneratePlayerSummaryServiceResult::success(
            new GenerateSummaryResult('A strong leader'),
            [$this->createAiLog()],
        );

        $this->expectException(LogicException::class);
        $result->getError();
    }

    public function testFailureIsNotSuccess(): void
    {
        $result = GeneratePlayerSummaryServiceResult::failure(
            [$this->createAiLog()],
            new RuntimeException('failed'),
        );

        self::assertFalse($result->isSuccess());
    }

    public function testFailureGetError(): void
    {
        $error = new RuntimeException('failed');
        $result = GeneratePlayerSummaryServiceResult::failure([$this->createAiLog()], $error);

        self::assertSame($error, $result->getError());
    }

    public function testFailureGetResultThrowsLogicException(): void
    {
        $result = GeneratePlayerSummaryServiceResult::failure(
            [$this->createAiLog()],
            new RuntimeException('failed'),
        );

        $this->expectException(LogicException::class);
        $result->getResult();
    }

    public function testFailureGetLogs(): void
    {
        $log = $this->createAiLog();
        $result = GeneratePlayerSummaryServiceResult::failure([$log], new RuntimeException('failed'));

        self::assertCount(1, $result->getLogs());
        self::assertSame($log, $result->getLogs()[0]);
    }

    private function createAiLog(): AiLog
    {
        return new AiLog(
            'gemini-2.5-flash',
            new DateTimeImmutable(),
            'test-action',
            'system prompt',
            'user prompt',
            '{}',
            0.7,
        );
    }
}
