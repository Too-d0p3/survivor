<?php

declare(strict_types=1);

namespace App\Domain\Game;

enum GameStatus: string
{
    case Setup = 'setup';
    case InProgress = 'in_progress';
    case Finished = 'finished';
}
