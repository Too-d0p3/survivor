<?php

declare(strict_types=1);

namespace App\Domain\Game;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<MajorEvent>
 */
class MajorEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MajorEvent::class);
    }

    /**
     * @return array<int, MajorEvent>
     */
    public function findByGameForPlayer(Uuid $gameId, Uuid $playerId, int $limit = 5): array
    {
        /** @var array<int, MajorEvent> */
        return $this->createQueryBuilder('me')
            ->innerJoin('me.participants', 'mep')
            ->addSelect('mep')
            ->where('me.game = :gameId')
            ->andWhere('mep.player = :playerId')
            ->setParameter('gameId', $gameId, 'uuid')
            ->setParameter('playerId', $playerId, 'uuid')
            ->orderBy('me.emotionalWeight', 'DESC')
            ->addOrderBy('me.tick', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, MajorEvent>
     */
    public function findByGameAndTick(Uuid $gameId, int $tick): array
    {
        /** @var array<int, MajorEvent> */
        return $this->createQueryBuilder('me')
            ->leftJoin('me.participants', 'mep')
            ->addSelect('mep')
            ->where('me.game = :gameId')
            ->andWhere('me.tick = :tick')
            ->setParameter('gameId', $gameId, 'uuid')
            ->setParameter('tick', $tick)
            ->orderBy('me.emotionalWeight', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, MajorEvent>
     */
    public function findByGame(Uuid $gameId): array
    {
        /** @var array<int, MajorEvent> */
        return $this->createQueryBuilder('me')
            ->leftJoin('me.participants', 'mep')
            ->addSelect('mep')
            ->where('me.game = :gameId')
            ->setParameter('gameId', $gameId, 'uuid')
            ->orderBy('me.tick', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
