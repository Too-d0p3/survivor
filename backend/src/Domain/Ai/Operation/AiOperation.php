<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiResponseSchema;

/**
 * @template T
 */
interface AiOperation
{
    public function getActionName(): string;

    public function getTemplateName(): string;

    /**
     * @return array<string, string>
     */
    public function getTemplateVariables(): array;

    /**
     * @return array<int, AiMessage>
     */
    public function getMessages(): array;

    public function getResponseSchema(): AiResponseSchema;

    public function getTemperature(): ?float;

    /**
     * @return T
     */
    public function parse(string $content): mixed;
}
