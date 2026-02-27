<?php

declare(strict_types=1);

namespace App\Domain\Relationship;

use App\Domain\Relationship\Exceptions\RelationshipNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Relationship>
 *
 * @method Relationship|null find($id, $lockMode = null, $lockVersion = null)
 * @method Relationship|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Relationship[]    findAll()
 * @method Relationship[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class RelationshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Relationship::class);
    }

    /**
     * @throws RelationshipNotFoundException
     */
    public function getRelationship(Uuid $relationshipId): Relationship
    {
        $relationship = $this->find($relationshipId);

        if ($relationship === null) {
            throw new RelationshipNotFoundException($relationshipId);
        }

        return $relationship;
    }

    /**
     * @throws RelationshipNotFoundException
     */
    public function getBySourceAndTarget(Uuid $sourceId, Uuid $targetId): Relationship
    {
        $relationship = $this->findOneBy(['source' => $sourceId->toString(), 'target' => $targetId->toString()]);

        if ($relationship === null) {
            throw new RelationshipNotFoundException($sourceId);
        }

        return $relationship;
    }

    /**
     * @return array<int, Relationship>
     */
    public function findByGame(Uuid $gameId): array
    {
        /** @var array<int, Relationship> */
        return $this->createQueryBuilder('relationship')
            ->join('relationship.source', 'player')
            ->where('player.game = :gameId')
            ->setParameter('gameId', $gameId->toString())
            ->getQuery()
            ->getResult();
    }
}
