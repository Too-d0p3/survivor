<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

use App\Domain\Ai\Log\AiLog;
use LogicException;
use Throwable;

final readonly class GenerateBatchPlayerSummariesServiceResult
{
    private bool $success;

    private ?GenerateBatchSummaryResult $result;

    /** @var array<int, AiLog> */
    private array $logs;

    private ?Throwable $error;

    /**
     * @param array<int, AiLog> $logs
     */
    private function __construct(
        bool $success,
        ?GenerateBatchSummaryResult $result,
        array $logs,
        ?Throwable $error,
    ) {
        $this->success = $success;
        $this->result = $result;
        $this->logs = $logs;
        $this->error = $error;
    }

    /**
     * @param array<int, AiLog> $logs
     */
    public static function success(GenerateBatchSummaryResult $result, array $logs): self
    {
        return new self(true, $result, $logs, null);
    }

    /**
     * @param array<int, AiLog> $logs
     */
    public static function failure(array $logs, Throwable $error): self
    {
        return new self(false, null, $logs, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getResult(): GenerateBatchSummaryResult
    {
        if (!$this->success) {
            throw new LogicException('Cannot get result from a failed GenerateBatchPlayerSummariesServiceResult');
        }

        assert($this->result instanceof GenerateBatchSummaryResult);

        return $this->result;
    }

    /**
     * @return array<int, AiLog>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getError(): Throwable
    {
        if ($this->success) {
            throw new LogicException('Cannot get error from a successful GenerateBatchPlayerSummariesServiceResult');
        }

        assert($this->error instanceof Throwable);

        return $this->error;
    }
}
