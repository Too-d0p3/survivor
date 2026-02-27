<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Game;
use App\Domain\User\User;
use RuntimeException;
use Throwable;

final class CannotProcessTickBecauseUserIsNotPlayerException extends RuntimeException
{
    private readonly Game $game;
    private readonly User $user;

    public function __construct(
        Game $game,
        User $user,
        ?Throwable $previous = null,
    ) {
        $this->game = $game;
        $this->user = $user;

        parent::__construct(
            sprintf('Cannot process tick for game `%s` because user `%s` is not a player', $game->getId(), $user->getId()),
            0,
            $previous,
        );
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
