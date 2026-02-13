<?php

declare(strict_types=1);

namespace App\Domain\Ai\Exceptions;

use RuntimeException;
use Throwable;

final class AiRequestFailedException extends RuntimeException
{
    private readonly string $actionName;

    private readonly ?int $httpStatusCode;

    public function __construct(
        string $actionName,
        ?int $httpStatusCode,
        string $detail,
        ?Throwable $previous = null,
    ) {
        $this->actionName = $actionName;
        $this->httpStatusCode = $httpStatusCode;

        $message = $httpStatusCode !== null
            ? sprintf("AI request '%s' failed with HTTP %d: %s", $actionName, $httpStatusCode, $detail)
            : sprintf("AI request '%s' failed: %s", $actionName, $detail);

        parent::__construct(
            $message,
            0,
            $previous,
        );
    }

    public function getActionName(): string
    {
        return $this->actionName;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }
}
