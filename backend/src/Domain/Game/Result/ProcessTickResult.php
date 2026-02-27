<?php

declare(strict_types=1);

namespace App\Domain\Game\Result;

use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;

final readonly class ProcessTickResult
{
    public Game $game;

    /** @var array<int, GameEvent> */
    public array $events;

    /**
     * @param array<int, GameEvent> $events
     */
    public function __construct(Game $game, array $events)
    {
        $this->game = $game;
        $this->events = $events;
    }
}
