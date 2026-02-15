<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class GenerateBatchSummaryResult
{
    /** @var array<int, string> */
    private array $summaries;

    /**
     * @param array<int, string> $summaries
     */
    public function __construct(array $summaries)
    {
        $this->summaries = $summaries;
    }

    /**
     * @return array<int, string>
     */
    public function getSummaries(): array
    {
        return $this->summaries;
    }
}
