<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\GenerateBatchPlayerSummariesServiceResult;
use App\Domain\Ai\Result\GenerateBatchSummaryResult;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GenerateBatchPlayerSummariesServiceResultTest extends TestCase
{
    public function testSuccessIsSuccess(): void
    {
        $result = GenerateBatchPlayerSummariesServiceResult::success(
            new GenerateBatchSummaryResult(['Leader.', 'Empath.']),
            [$this->createAiLog()],
        );

        self::assertTrue($result->isSuccess());
    }

    public function testSuccessGetResult(): void
    {
        $batchResult = new GenerateBatchSummaryResult(['Leader.', 'Empath.']);
        $result = GenerateBatchPlayerSummariesServiceResult::success($batchResult, [$this->createAiLog()]);

        self::assertSame($batchResult, $result->getResult());
    }

    public function testSuccessGetLogs(): void
    {
        $log = $this->createAiLog();
        $result = GenerateBatchPlayerSummariesServiceResult::success(
            new GenerateBatchSummaryResult(['Leader.']),
            [$log],
        );

        self::assertCount(1, $result->getLogs());
        self::assertSame($log, $result->getLogs()[0]);
    }

    public function testSuccessGetErrorThrowsLogicException(): void
    {
        $result = GenerateBatchPlayerSummariesServiceResult::success(
            new GenerateBatchSummaryResult(['Leader.']),
            [$this->createAiLog()],
        );

        $this->expectException(LogicException::class);
        $result->getError();
    }

    public function testFailureIsNotSuccess(): void
    {
        $result = GenerateBatchPlayerSummariesServiceResult::failure(
            [$this->createAiLog()],
            new RuntimeException('failed'),
        );

        self::assertFalse($result->isSuccess());
    }

    public function testFailureGetError(): void
    {
        $error = new RuntimeException('failed');
        $result = GenerateBatchPlayerSummariesServiceResult::failure([$this->createAiLog()], $error);

        self::assertSame($error, $result->getError());
    }

    public function testFailureGetResultThrowsLogicException(): void
    {
        $result = GenerateBatchPlayerSummariesServiceResult::failure(
            [$this->createAiLog()],
            new RuntimeException('failed'),
        );

        $this->expectException(LogicException::class);
        $result->getResult();
    }

    public function testFailureGetLogs(): void
    {
        $log = $this->createAiLog();
        $result = GenerateBatchPlayerSummariesServiceResult::failure([$log], new RuntimeException('failed'));

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
