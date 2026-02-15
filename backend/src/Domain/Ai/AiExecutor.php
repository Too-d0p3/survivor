<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Ai\Operation\AiOperation;
use App\Domain\Ai\Result\AiCallResult;
use DateTimeImmutable;

interface AiExecutor
{
    /**
     * Executes an AI operation and returns the result. Never throws — always returns AiCallResult.
     *
     * @template T
     * @param AiOperation<T> $operation
     * @return AiCallResult<T>
     */
    public function execute(AiOperation $operation, DateTimeImmutable $now): AiCallResult;
}
