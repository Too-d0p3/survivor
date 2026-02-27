<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

use App\Domain\Ai\Log\AiLog;
use LogicException;
use Throwable;

final readonly class SimulateTickServiceResult
{
    private bool $success;

    private ?SimulateTickResult $result;

    /** @var array<int, AiLog> */
    private array $logs;

    private ?Throwable $error;

    /**
     * @param array<int, AiLog> $logs
     */
    private function __construct(
        bool $success,
        ?SimulateTickResult $result,
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
    public static function success(SimulateTickResult $result, array $logs): self
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

    public function getResult(): SimulateTickResult
    {
        if (!$this->success) {
            throw new LogicException('Cannot get result from a failed SimulateTickServiceResult');
        }

        assert($this->result instanceof SimulateTickResult);

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
            throw new LogicException('Cannot get error from a successful SimulateTickServiceResult');
        }

        assert($this->error instanceof Throwable);

        return $this->error;
    }
}
