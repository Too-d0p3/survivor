<?php

declare(strict_types=1);

namespace App\Domain\Ai\Exceptions;

use RuntimeException;

final class PromptTemplateNotFoundException extends RuntimeException
{
    public function __construct(string $templateName)
    {
        parent::__construct(
            sprintf("Prompt template '%s' not found", $templateName),
            0,
        );
    }
}
