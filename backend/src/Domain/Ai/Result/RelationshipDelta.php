<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class RelationshipDelta
{
    public int $sourceIndex;

    public int $targetIndex;

    public int $trustDelta;

    public int $affinityDelta;

    public int $respectDelta;

    public int $threatDelta;

    public function __construct(
        int $sourceIndex,
        int $targetIndex,
        int $trustDelta,
        int $affinityDelta,
        int $respectDelta,
        int $threatDelta,
    ) {
        $this->sourceIndex = $sourceIndex;
        $this->targetIndex = $targetIndex;
        $this->trustDelta = $trustDelta;
        $this->affinityDelta = $affinityDelta;
        $this->respectDelta = $respectDelta;
        $this->threatDelta = $threatDelta;
    }
}
