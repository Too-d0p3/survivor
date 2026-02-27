<?php

declare(strict_types=1);

namespace App\Domain\Game\Result;

use App\Domain\Ai\Result\SimulateTickResult;
use App\Domain\Game\Game;

final readonly class PreviewTickResult
{
    public Game $game;

    public SimulateTickResult $simulation;

    public function __construct(Game $game, SimulateTickResult $simulation)
    {
        $this->game = $game;
        $this->simulation = $simulation;
    }
}
