<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Exceptions\GameNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Game>
 *
 * @method Game|null find($id, $lockMode = null, $lockVersion = null)
 * @method Game|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Game[]    findAll()
 * @method Game[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @throws GameNotFoundException
     */
    public function getGame(Uuid $gameId): Game
    {
        $game = $this->find($gameId);

        if ($game === null) {
            throw new GameNotFoundException($gameId);
        }

        return $game;
    }
}
