<?php

declare(strict_types=1);

namespace App\Domain\Game;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<GameEvent>
 *
 * @method GameEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method GameEvent|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method GameEvent[]    findAll()
 * @method GameEvent[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class GameEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameEvent::class);
    }

    /**
     * @return array<int, GameEvent>
     */
    public function findByGamePaginated(Uuid $gameId, int $limit, int $offset): array
    {
        /** @var array<int, GameEvent> $result */
        $result = $this->createQueryBuilder('gameEvent')
            ->where('gameEvent.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->orderBy('gameEvent.tick', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<int, GameEvent>
     */
    public function findByGameFromTick(Uuid $gameId, int $fromTick): array
    {
        /** @var array<int, GameEvent> $result */
        $result = $this->createQueryBuilder('gameEvent')
            ->where('gameEvent.game = :gameId')
            ->andWhere('gameEvent.tick >= :fromTick')
            ->setParameter('gameId', $gameId)
            ->setParameter('fromTick', $fromTick)
            ->orderBy('gameEvent.tick', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countByGame(Uuid $gameId): int
    {
        return (int) $this->createQueryBuilder('gameEvent')
            ->select('COUNT(gameEvent.id)')
            ->where('gameEvent.game = :gameId')
            ->setParameter('gameId', $gameId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
