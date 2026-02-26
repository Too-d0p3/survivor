<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Player\Exceptions\PlayerNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Player>
 *
 * @method Player|null find($id, $lockMode = null, $lockVersion = null)
 * @method Player|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Player[]    findAll()
 * @method Player[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /**
     * @throws PlayerNotFoundException
     */
    public function getPlayer(Uuid $playerId): Player
    {
        $player = $this->find($playerId);

        if ($player === null) {
            throw new PlayerNotFoundException($playerId);
        }

        return $player;
    }
}
