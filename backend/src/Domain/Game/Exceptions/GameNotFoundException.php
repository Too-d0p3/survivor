<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use RuntimeException;
use Throwable;

final class GameNotFoundException extends RuntimeException
{
    private readonly string $gameId;

    public function __construct(
        string $gameId,
        ?Throwable $previous = null,
    ) {
        $this->gameId = $gameId;

        parent::__construct(
            sprintf('Game with id `%s` not found', $gameId),
            0,
            $previous,
        );
    }

    public function getGameId(): string
    {
        return $this->gameId;
    }
}
