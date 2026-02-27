<?php

declare(strict_types=1);

namespace App\Domain\Game\Enum;

enum GameEventType: string
{
    case GameStarted = 'game_started';
    case PlayerAction = 'player_action';
    case NightSleep = 'night_sleep';
    case TickSimulation = 'tick_simulation';
    case PlayerPerspective = 'player_perspective';
}
