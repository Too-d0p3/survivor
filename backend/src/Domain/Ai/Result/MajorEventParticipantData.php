<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class MajorEventParticipantData
{
    public int $playerIndex;

    public string $role;

    public function __construct(int $playerIndex, string $role)
    {
        $this->playerIndex = $playerIndex;
        $this->role = $role;
    }
}
