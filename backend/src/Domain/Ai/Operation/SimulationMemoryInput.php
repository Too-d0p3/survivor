<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

final readonly class SimulationMemoryInput
{
    private int $playerIndex;

    private int $day;

    private int $hour;

    private string $type;

    private string $summary;

    private int $emotionalWeight;

    private string $role;

    public function __construct(
        int $playerIndex,
        int $day,
        int $hour,
        string $type,
        string $summary,
        int $emotionalWeight,
        string $role,
    ) {
        $this->playerIndex = $playerIndex;
        $this->day = $day;
        $this->hour = $hour;
        $this->type = $type;
        $this->summary = $summary;
        $this->emotionalWeight = $emotionalWeight;
        $this->role = $role;
    }

    public function getPlayerIndex(): int
    {
        return $this->playerIndex;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function getHour(): int
    {
        return $this->hour;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getEmotionalWeight(): int
    {
        return $this->emotionalWeight;
    }

    public function getRole(): string
    {
        return $this->role;
    }
}
