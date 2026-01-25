<?php

namespace App\Domain\Ai\Log;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiLog>
 *
 * @method AiLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method AiLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method AiLog[]    findAll()
 * @method AiLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AiLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiLog::class);
    }

//    /**
//     * @return AiLog[] Returns an array of AiLog objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?AiLog
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
} 