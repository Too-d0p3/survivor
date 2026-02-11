<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

class GameService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function createGameForUser(User $user): Game
    {
        $game = new Game();
        $game->setOwner($user);
        $this->em->persist($game);

        $this->em->flush();
        return $game;
    }
}
