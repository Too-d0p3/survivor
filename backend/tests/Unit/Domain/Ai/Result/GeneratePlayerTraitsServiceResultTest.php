<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\GeneratePlayerTraitsServiceResult;
use App\Domain\Ai\Result\GenerateTraitsResult;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeneratePlayerTraitsServiceResultTest extends TestCase
{
    public function testSuccessIsSuccess(): void
    {
        $result = GeneratePlayerTraitsServiceResult::success(
            new GenerateTraitsResult(['leadership' => 0.8], 'A leader'),
            [$this->createAiLog()],
        );

        self::assertTrue($result->isSuccess());
    }

    public function testSuccessGetResult(): void
    {
        $traitsResult = new GenerateTraitsResult(['leadership' => 0.8], 'A leader');
        $result = GeneratePlayerTraitsServiceResult::success($traitsResult, [$this->createAiLog()]);

        self::assertSame($traitsResult, $result->getResult());
    }

    public function testSuccessGetLogs(): void
    {
        $log = $this->createAiLog();
        $result = GeneratePlayerTraitsServiceResult::success(
            new GenerateTraitsResult(['leadership' => 0.8], 'A leader'),
            [$log],
        );

        self::assertCount(1, $result->getLogs());
        self::assertSame($log, $result->getLogs()[0]);
    }

    public function testSuccessGetErrorThrowsLogicException(): void
    {
        $result = GeneratePlayerTraitsServiceResult::success(
            new GenerateTraitsResult(['leadership' => 0.8], 'A leader'),
            [$this->createAiLog()],
        );

        $this->expectException(LogicException::class);
        $result->getError();
    }

    public function testFailureIsNotSuccess(): void
    {
        $result = GeneratePlayerTraitsServiceResult::failure(
            [$this->createAiLog()],
            new RuntimeException('failed'),
        );

        self::assertFalse($result->isSuccess());
    }

    public function testFailureGetError(): void
    {
        $error = new RuntimeException('failed');
        $result = GeneratePlayerTraitsServiceResult::failure([$this->createAiLog()], $error);

        self::assertSame($error, $result->getError());
    }

    public function testFailureGetResultThrowsLogicException(): void
    {
        $result = GeneratePlayerTraitsServiceResult::failure(
            [$this->createAiLog()],
            new RuntimeException('failed'),
        );

        $this->expectException(LogicException::class);
        $result->getResult();
    }

    public function testFailureGetLogs(): void
    {
        $log = $this->createAiLog();
        $result = GeneratePlayerTraitsServiceResult::failure([$log], new RuntimeException('failed'));

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
