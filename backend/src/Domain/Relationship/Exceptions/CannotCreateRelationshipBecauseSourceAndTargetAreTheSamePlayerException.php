<?php

declare(strict_types=1);

namespace App\Domain\Relationship\Exceptions;

use App\Domain\Player\Player;
use RuntimeException;
use Throwable;

final class CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException extends RuntimeException
{
    private readonly Player $player;

    public function __construct(
        Player $player,
        ?Throwable $previous = null,
    ) {
        $this->player = $player;

        parent::__construct(
            sprintf(
                'Cannot create relationship because source and target are the same player `%s`',
                $player->getId()->toString(),
            ),
            0,
            $previous,
        );
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }
}
