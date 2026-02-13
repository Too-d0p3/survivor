<?php

declare(strict_types=1);

namespace App\Domain\Ai\Log;

enum AiLogStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Error = 'error';
}
