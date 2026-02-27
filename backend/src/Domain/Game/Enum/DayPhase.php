<?php

declare(strict_types=1);

namespace App\Domain\Game\Enum;

enum DayPhase: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Night = 'night';

    public static function fromHour(int $hour): self
    {
        return match (true) {
            $hour >= 6 && $hour <= 10 => self::Morning,
            $hour >= 12 && $hour <= 16 => self::Afternoon,
            $hour >= 18 && $hour <= 22 => self::Evening,
            default => self::Night,
        };
    }
}
