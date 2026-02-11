<?php

declare(strict_types=1);

namespace App\Domain\TraitDef;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TraitDef>
 *
 * @method TraitDef|null find($id, $lockMode = null, $lockVersion = null)
 * @method TraitDef|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method TraitDef[]    findAll()
 * @method TraitDef[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class TraitDefRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TraitDef::class);
    }
}
