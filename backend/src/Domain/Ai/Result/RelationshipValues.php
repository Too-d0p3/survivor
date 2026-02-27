<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class RelationshipValues
{
    public int $sourceIndex;

    public int $targetIndex;

    public int $trust;

    public int $affinity;

    public int $respect;

    public int $threat;

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
}
