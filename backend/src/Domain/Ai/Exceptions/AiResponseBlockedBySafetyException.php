<?php

declare(strict_types=1);

namespace App\Domain\Ai\Exceptions;

use RuntimeException;
use Throwable;

final class AiResponseBlockedBySafetyException extends RuntimeException
{
    private readonly string $actionName;

    public function __construct(
        string $actionName,
        ?Throwable $previous = null,
    ) {
        $this->actionName = $actionName;

        parent::__construct(
            sprintf("AI response for action '%s' was blocked by safety filter", $actionName),
            0,
            $previous,
        );
    }

    public function getActionName(): string
    {
        return $this->actionName;
    }
}
