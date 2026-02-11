<?php

declare(strict_types=1);

namespace App\Domain\Ai\Log;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiLog>
 *
 * @method AiLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AiLog|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method AiLog[]    findAll()
 * @method AiLog[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class AiLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiLog::class);
    }
}
