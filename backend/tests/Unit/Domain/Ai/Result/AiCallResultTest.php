<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\AiCallResult;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AiCallResultTest extends TestCase
{
    public function testSuccessIsSuccess(): void
    {
        $log = $this->createAiLog();
        $result = AiCallResult::success('parsed-data', $log);

        self::assertTrue($result->isSuccess());
    }

    public function testSuccessGetResult(): void
    {
        $log = $this->createAiLog();
        $result = AiCallResult::success('parsed-data', $log);

        self::assertSame('parsed-data', $result->getResult());
    }

    public function testSuccessGetLog(): void
    {
        $log = $this->createAiLog();
        $result = AiCallResult::success('parsed-data', $log);

        self::assertSame($log, $result->getLog());
    }

    public function testSuccessGetErrorThrowsLogicException(): void
    {
        $log = $this->createAiLog();
        $result = AiCallResult::success('parsed-data', $log);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot get error from a successful AiCallResult');

        $result->getError();
    }

    public function testFailureIsNotSuccess(): void
    {
        $log = $this->createAiLog();
        $error = new RuntimeException('something failed');
        $result = AiCallResult::failure($log, $error);

        self::assertFalse($result->isSuccess());
    }

    public function testFailureGetError(): void
    {
        $log = $this->createAiLog();
        $error = new RuntimeException('something failed');
        $result = AiCallResult::failure($log, $error);

        self::assertSame($error, $result->getError());
    }

    public function testFailureGetLog(): void
    {
        $log = $this->createAiLog();
        $error = new RuntimeException('something failed');
        $result = AiCallResult::failure($log, $error);

        self::assertSame($log, $result->getLog());
    }

    public function testFailureGetResultThrowsLogicException(): void
    {
        $log = $this->createAiLog();
        $error = new RuntimeException('something failed');
        $result = AiCallResult::failure($log, $error);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot get result from a failed AiCallResult');

        $result->getResult();
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
