<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

final readonly class SimulationRelationshipInput
{
    private int $sourceIndex;

    private int $targetIndex;

    private int $trust;

    private int $affinity;

    private int $respect;

    private int $threat;

    public function __construct(
        int $sourceIndex,
        int $targetIndex,
        int $trust,
        int $affinity,
        int $respect,
        int $threat,
    ) {
        $this->sourceIndex = $sourceIndex;
        $this->targetIndex = $targetIndex;
        $this->trust = $trust;
        $this->affinity = $affinity;
        $this->respect = $respect;
        $this->threat = $threat;
    }

    public function getSourceIndex(): int
    {
        return $this->sourceIndex;
    }

    public function getTargetIndex(): int
    {
        return $this->targetIndex;
    }

    public function getTrust(): int
    {
        return $this->trust;
    }

    public function getAffinity(): int
    {
        return $this->affinity;
    }

    public function getRespect(): int
    {
        return $this->respect;
    }

    public function getThreat(): int
    {
        return $this->threat;
    }
}
