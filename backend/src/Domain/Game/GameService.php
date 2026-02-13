<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Exceptions\CannotDeleteGameBecauseUserIsNotOwnerException;
use App\Domain\User\User;

final class GameService
{
    /**
     * @throws CannotDeleteGameBecauseUserIsNotOwnerException
     */
    public function deleteGame(Game $game, User $requestingUser): Game
    {
        if ($game->getOwner() !== $requestingUser) {
            throw new CannotDeleteGameBecauseUserIsNotOwnerException($game, $requestingUser);
        }

        return $game;
    }
}
