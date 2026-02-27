<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Game;
use RuntimeException;
use Throwable;

final class CannotProcessTickBecauseGameIsNotInProgressException extends RuntimeException
{
    private readonly Game $game;

    public function __construct(
        Game $game,
        ?Throwable $previous = null,
    ) {
        $this->game = $game;

        parent::__construct(
            sprintf('Cannot process tick for game `%s` because it is not in progress', $game->getId()),
            0,
            $previous,
        );
    }

    public function getGame(): Game
    {
        return $this->game;
    }
}
