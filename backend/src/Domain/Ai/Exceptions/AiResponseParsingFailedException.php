<?php

declare(strict_types=1);

namespace App\Domain\Ai\Exceptions;

use RuntimeException;
use Throwable;

final class AiResponseParsingFailedException extends RuntimeException
{
    private readonly string $actionName;

    private readonly string $rawContent;

    public function __construct(
        string $actionName,
        string $rawContent,
        string $detail,
        ?Throwable $previous = null,
    ) {
        $this->actionName = $actionName;
        $this->rawContent = $rawContent;

        parent::__construct(
            sprintf("Failed to parse AI response for action '%s': %s", $actionName, $detail),
            0,
            $previous,
        );
    }

    public function getActionName(): string
    {
        return $this->actionName;
    }

    public function getRawContent(): string
    {
        return $this->rawContent;
    }
}
