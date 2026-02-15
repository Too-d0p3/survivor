<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Service;

use App\Domain\Ai\AiExecutor;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Operation\AiOperation;
use App\Domain\Ai\Result\AiCallResult;
use App\Domain\Ai\Result\GenerateBatchSummaryResult;
use App\Domain\Ai\Result\GenerateTraitsResult;
use App\Domain\Ai\Service\AiPlayerService;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Throwable;

final class AiPlayerServiceTest extends TestCase
{
    public function testGeneratePlayerTraitsFromDescriptionReturnsSuccessResult(): void
    {
        $traitsResult = new GenerateTraitsResult(['leadership' => 0.8], 'A leader');
        $log = $this->createAiLog();
        $executor = $this->createSuccessExecutor($traitsResult, $log);
        $service = new AiPlayerService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $traits = [new TraitDef('leadership', 'Leadership', 'Leading', TraitType::Social)];

        $result = $service->generatePlayerTraitsFromDescription('A strong leader', $traits, $now);

        self::assertTrue($result->isSuccess());
        self::assertSame($traitsResult, $result->getResult());
        self::assertCount(1, $result->getLogs());
        self::assertSame($log, $result->getLogs()[0]);
    }

    public function testGeneratePlayerTraitsFromDescriptionReturnsFailureResult(): void
    {
        $log = $this->createAiLog();
        $error = new AiRequestFailedException('test', 500, 'Failed');
        $executor = $this->createFailureExecutor($log, $error);
        $service = new AiPlayerService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $traits = [new TraitDef('leadership', 'Leadership', 'Leading', TraitType::Social)];

        $result = $service->generatePlayerTraitsFromDescription('A strong leader', $traits, $now);

        self::assertFalse($result->isSuccess());
        self::assertSame($error, $result->getError());
        self::assertCount(1, $result->getLogs());
    }

    public function testGeneratePlayerTraitsSummaryDescriptionReturnsSuccessResult(): void
    {
        $batchResult = new GenerateBatchSummaryResult(['Summary from traits.']);
        $log = $this->createAiLog();
        $executor = $this->createSuccessExecutor($batchResult, $log);
        $service = new AiPlayerService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $result = $service->generatePlayerTraitsSummaryDescription(['leadership' => '0.85'], $now);

        self::assertTrue($result->isSuccess());
        self::assertSame('Summary from traits.', $result->getResult()->getSummary());
        self::assertCount(1, $result->getLogs());
    }

    public function testGeneratePlayerTraitsSummaryDescriptionReturnsFailureResult(): void
    {
        $log = $this->createAiLog();
        $error = new AiRequestFailedException('test', 500, 'Failed');
        $executor = $this->createFailureExecutor($log, $error);
        $service = new AiPlayerService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $result = $service->generatePlayerTraitsSummaryDescription(['leadership' => '0.85'], $now);

        self::assertFalse($result->isSuccess());
        self::assertSame($error, $result->getError());
    }

    public function testGenerateBatchPlayerTraitsSummaryDescriptionsReturnsSuccessResult(): void
    {
        $batchResult = new GenerateBatchSummaryResult(['Leader summary.', 'Empath summary.']);
        $log = $this->createAiLog();
        $executor = $this->createSuccessExecutor($batchResult, $log);
        $service = new AiPlayerService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $playerTraitStrengths = [
            ['leadership' => '0.85', 'empathy' => '0.60'],
            ['leadership' => '0.30', 'empathy' => '0.90'],
        ];

        $result = $service->generateBatchPlayerTraitsSummaryDescriptions($playerTraitStrengths, $now);

        self::assertTrue($result->isSuccess());
        self::assertSame(['Leader summary.', 'Empath summary.'], $result->getResult()->getSummaries());
        self::assertCount(1, $result->getLogs());
    }

    public function testGenerateBatchPlayerTraitsSummaryDescriptionsReturnsFailureResult(): void
    {
        $log = $this->createAiLog();
        $error = new AiRequestFailedException('test', 500, 'Failed');
        $executor = $this->createFailureExecutor($log, $error);
        $service = new AiPlayerService($executor);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $playerTraitStrengths = [['leadership' => '0.85']];

        $result = $service->generateBatchPlayerTraitsSummaryDescriptions($playerTraitStrengths, $now);

        self::assertFalse($result->isSuccess());
        self::assertSame($error, $result->getError());
        self::assertCount(1, $result->getLogs());
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
