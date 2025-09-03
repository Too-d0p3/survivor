<?php

namespace App\Domain\Player\Trait;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayerTrait>
 *
 * @method PlayerTrait|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlayerTrait|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlayerTrait[]    findAll()
 * @method PlayerTrait[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerTraitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayerTrait::class);
    }
} 