<?php

declare(strict_types=1);

namespace App\Domain\Game\Enum;

enum MajorEventType: string
{
    case Betrayal = 'betrayal';
    case Alliance = 'alliance';
    case Conflict = 'conflict';
    case Revelation = 'revelation';
    case Sacrifice = 'sacrifice';
    case Manipulation = 'manipulation';
    case Other = 'other';
}
