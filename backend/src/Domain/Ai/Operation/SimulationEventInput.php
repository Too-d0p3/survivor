<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

final readonly class SimulationEventInput
{
    private int $day;

    private int $hour;

    private string $type;

    private ?string $playerName;

    private ?string $narrative;

    private ?string $actionText;

    public function __construct(
        int $day,
        int $hour,
        string $type,
        ?string $playerName,
        ?string $narrative,
        ?string $actionText,
    ) {
        $this->day = $day;
        $this->hour = $hour;
        $this->type = $type;
        $this->playerName = $playerName;
        $this->narrative = $narrative;
        $this->actionText = $actionText;
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

    public function getPlayerName(): ?string
    {
        return $this->playerName;
    }

    public function getNarrative(): ?string
    {
        return $this->narrative;
    }

    public function getActionText(): ?string
    {
        return $this->actionText;
    }
}
