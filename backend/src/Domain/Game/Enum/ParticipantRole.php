<?php

declare(strict_types=1);

namespace App\Domain\Game\Enum;

enum ParticipantRole: string
{
    case Initiator = 'initiator';
    case Target = 'target';
    case Witness = 'witness';
}
