<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class GenerateSummaryResult
{
    private string $summary;

    public function __construct(string $summary)
    {
        $this->summary = $summary;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }
}
