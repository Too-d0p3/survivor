<?php

namespace App\Domain\Game;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

class GameService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function createGameForUser(User $user): Game
    {
        $game = new Game();
        $game->setOwner($user);
        $this->em->persist($game);

        $this->em->flush();
        return $game;
    }
}