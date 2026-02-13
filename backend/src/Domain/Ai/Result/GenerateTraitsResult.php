<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class GenerateTraitsResult
{
    /** @var array<string, float> */
    private array $traitScores;
    private string $summary;

    /**
     * @param array<string, float> $traitScores
     */
    public function __construct(
        array $traitScores,
        string $summary,
    ) {
        $this->traitScores = $traitScores;
        $this->summary = $summary;
    }

    /**
     * @return array<string, float>
     */
    public function getTraitScores(): array
    {
        return $this->traitScores;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }
}
