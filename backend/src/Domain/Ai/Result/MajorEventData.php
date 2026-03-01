<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class MajorEventData
{
    public string $type;

    public string $summary;

    public int $emotionalWeight;

    /** @var array<int, MajorEventParticipantData> */
    public array $participants;

    /**
     * @param array<int, MajorEventParticipantData> $participants
     */
    public function __construct(string $type, string $summary, int $emotionalWeight, array $participants)
    {
        $this->type = $type;
        $this->summary = $summary;
        $this->emotionalWeight = $emotionalWeight;
        $this->participants = $participants;
    }
}
