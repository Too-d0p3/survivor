<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class TokenUsage
{
    private int $promptTokenCount;
    private int $candidatesTokenCount;
    private int $totalTokenCount;

    public function __construct(
        int $promptTokenCount,
        int $candidatesTokenCount,
        int $totalTokenCount,
    ) {
        $this->promptTokenCount = $promptTokenCount;
        $this->candidatesTokenCount = $candidatesTokenCount;
        $this->totalTokenCount = $totalTokenCount;
    }

    public function getPromptTokenCount(): int
    {
        return $this->promptTokenCount;
    }

    public function getCandidatesTokenCount(): int
    {
        return $this->candidatesTokenCount;
    }

    public function getTotalTokenCount(): int
    {
        return $this->totalTokenCount;
    }
}
