<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Game;
use App\Domain\User\User;
use RuntimeException;
use Throwable;

final class CannotDeleteGameBecauseUserIsNotOwnerException extends RuntimeException
{
    private readonly Game $game;
    private readonly User $requestingUser;

    public function __construct(
        Game $game,
        User $requestingUser,
        ?Throwable $previous = null,
    ) {
        $this->game = $game;
        $this->requestingUser = $requestingUser;

        parent::__construct(
            sprintf('Cannot delete game `%s` because user `%s` is not the owner', $game->getId(), $requestingUser->getId()),
            0,
            $previous,
        );
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getRequestingUser(): User
    {
        return $this->requestingUser;
    }
}
