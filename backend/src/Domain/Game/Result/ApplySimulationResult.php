<?php

declare(strict_types=1);

namespace App\Domain\Game\Result;

use App\Domain\Game\GameEvent;

final readonly class ApplySimulationResult
{
    /** @var array<int, GameEvent> */
    public array $events;

    /**
     * @param array<int, GameEvent> $events
     */
    public function __construct(array $events)
    {
        $this->events = $events;
    }
}
