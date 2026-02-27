<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Ai\AiExecutor;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Operation\AiOperation;
use App\Domain\Ai\Operation\SimulationPlayerInput;
use App\Domain\Ai\Result\AiCallResult;
use App\Domain\Ai\Result\SimulateTickResult;
use App\Domain\Game\SimulationAiService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SimulationAiServiceTest extends TestCase
{
    public function testSimulateTickReturnsSuccessResult(): void
    {
        $tickResult = new SimulateTickResult(
            'reasoning',
            'pláž',
            [2],
            'macro narativ',
            'player narativ',
            [],
        );
        $log = $this->createAiLog();
        $executor = $this->createSuccessExecutor($tickResult, $log);
        $service = new SimulationAiService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $result = $service->simulateTick(
            1,
            8,
            'Jdu sbírat dříví',
            $this->createPlayers(),
            [],
            [],
            1,
            $now,
        );

        self::assertTrue($result->isSuccess());
        self::assertSame($tickResult, $result->getResult());
        self::assertCount(1, $result->getLogs());
        self::assertSame($log, $result->getLogs()[0]);
    }

    public function testSimulateTickReturnsFailureResult(): void
    {
        $log = $this->createAiLog();
        $error = new AiRequestFailedException('test', 500, 'Failed');
        $executor = $this->createFailureExecutor($log, $error);
        $service = new SimulationAiService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $result = $service->simulateTick(
            1,
            8,
            'Jdu sbírat dříví',
            $this->createPlayers(),
            [],
            [],
            1,
            $now,
        );

        self::assertFalse($result->isSuccess());
        self::assertSame($error, $result->getError());
        self::assertCount(1, $result->getLogs());
    }

    /**
     * @return array<int, SimulationPlayerInput>
     */
    private function createPlayers(): array
    {
        return [
            new SimulationPlayerInput(1, 'Ondra', 'Popis Ondry', ['loyal' => '0.72'], true),
            new SimulationPlayerInput(2, 'Alex', 'Popis Alexe', ['strategic' => '0.85'], false),
        ];
    }

    private function createSuccessExecutor(mixed $parsedResult, AiLog $log): AiExecutor
    {
        return new readonly class ($parsedResult, $log) implements AiExecutor {
            private mixed $parsedResult;

            private AiLog $log;

            public function __construct(mixed $parsedResult, AiLog $log)
            {
                $this->parsedResult = $parsedResult;
                $this->log = $log;
            }

            /**
             * @template T
             * @param AiOperation<T> $operation
             * @return AiCallResult<T>
             */
            public function execute(AiOperation $operation, DateTimeImmutable $now): AiCallResult
            {
                /** @var AiCallResult<T> */
                return AiCallResult::success($this->parsedResult, $this->log);
            }
        };
    }

    private function createFailureExecutor(AiLog $log, Throwable $error): AiExecutor
    {
        return new readonly class ($log, $error) implements AiExecutor {
            private AiLog $log;

            private Throwable $error;

            public function __construct(AiLog $log, Throwable $error)
            {
                $this->log = $log;
                $this->error = $error;
            }

            /**
             * @template T
             * @param AiOperation<T> $operation
             * @return AiCallResult<T>
             */
            public function execute(AiOperation $operation, DateTimeImmutable $now): AiCallResult
            {
                return AiCallResult::failure($this->log, $this->error);
            }
        };
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
