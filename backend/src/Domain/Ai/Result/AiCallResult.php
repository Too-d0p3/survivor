<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

use App\Domain\Ai\Log\AiLog;
use LogicException;
use Throwable;

/**
 * @template-covariant T
 */
final readonly class AiCallResult
{
    private bool $success;

    private mixed $parsedResult;

    private AiLog $log;

    private ?Throwable $error;

    private function __construct(
        bool $success,
        mixed $parsedResult,
        AiLog $log,
        ?Throwable $error,
    ) {
        $this->success = $success;
        $this->parsedResult = $parsedResult;
        $this->log = $log;
        $this->error = $error;
    }

    /**
     * @template TSuccess
     * @param TSuccess $parsedResult
     * @return self<TSuccess>
     */
    public static function success(mixed $parsedResult, AiLog $log): self
    {
        /** @var self<TSuccess> */
        return new self(true, $parsedResult, $log, null);
    }

    /**
     * @return self<never>
     */
    public static function failure(AiLog $log, Throwable $error): self
    {
        /** @var self<never> */
        return new self(false, null, $log, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return T
     */
    public function getResult(): mixed
    {
        if (!$this->success) {
            throw new LogicException('Cannot get result from a failed AiCallResult');
        }

        /** @var T */
        return $this->parsedResult;
    }

    public function getLog(): AiLog
    {
        return $this->log;
    }

    public function getError(): Throwable
    {
        if ($this->success) {
            throw new LogicException('Cannot get error from a successful AiCallResult');
        }

        assert($this->error instanceof Throwable);

        return $this->error;
    }
}
