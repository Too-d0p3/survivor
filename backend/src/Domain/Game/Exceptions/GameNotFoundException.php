<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class GameNotFoundException extends RuntimeException
{
    private readonly Uuid $gameId;

    public function __construct(
        Uuid $gameId,
        ?Throwable $previous = null,
    ) {
        $this->gameId = $gameId;

        parent::__construct(
            sprintf('Game with id `%s` not found', $gameId->toString()),
            0,
            $previous,
        );
    }

    public function getGameId(): Uuid
    {
        return $this->gameId;
    }
}
