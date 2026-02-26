<?php

declare(strict_types=1);

namespace App\Domain\Player\Exceptions;

use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class PlayerNotFoundException extends RuntimeException
{
    private readonly Uuid $playerId;

    public function __construct(
        Uuid $playerId,
        ?Throwable $previous = null,
    ) {
        $this->playerId = $playerId;

        parent::__construct(
            sprintf('Player with id `%s` not found', $playerId->toString()),
            0,
            $previous,
        );
    }

    public function getPlayerId(): Uuid
    {
        return $this->playerId;
    }
}
