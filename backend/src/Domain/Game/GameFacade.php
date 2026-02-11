<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\User\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class GameFacade
{
    private readonly EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createGameForUser(User $user): Game
    {
        $game = new Game($user, false, new DateTimeImmutable());
        $this->entityManager->persist($game);

        $this->entityManager->flush();
        return $game;
    }
}
