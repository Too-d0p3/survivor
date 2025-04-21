<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
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