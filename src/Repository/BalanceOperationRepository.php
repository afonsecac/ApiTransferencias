<?php

namespace App\Repository;

use App\Entity\BalanceOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BalanceOperation>
 *
 * @method BalanceOperation|null find($id, $lockMode = null, $lockVersion = null)
 * @method BalanceOperation|null findOneBy(array $criteria, array $orderBy = null)
 * @method BalanceOperation[]    findAll()
 * @method BalanceOperation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BalanceOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BalanceOperation::class);
    }

    public function getBalanceOutput(int $userId): float
    {
        try {
            $dql = $this->createQueryBuilder('b')
                ->leftJoin('b.tenant', 'a')
                ->select('SUM(b.totalAmount) as total')
                ->where('b.state = :completed')
                ->andWhere('a.id = :tenantId')
                ->setParameter('completed', 'COMPLETED')
                ->setParameter('tenantId', $userId);


            $info = $dql->getQuery()->getSingleScalarResult();
            if (is_null($info)) {
                $info = 0;
            }
            return $info;
        } catch (\Exception $e) {
            return 0;
        }
    }

//    /**
//     * @return BalanceOperation[] Returns an array of BalanceOperation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?BalanceOperation
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
