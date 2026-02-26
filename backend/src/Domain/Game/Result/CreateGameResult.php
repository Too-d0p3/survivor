<?php

declare(strict_types=1);

namespace App\Domain\Game\Result;

use App\Domain\Game\Game;

final readonly class CreateGameResult
{
    public Game $game;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }
}
