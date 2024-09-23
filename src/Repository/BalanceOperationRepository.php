<?php

namespace App\Repository;

use App\Entity\BalanceOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getBalanceByTransferId(int $transferId, int $userId): BalanceOperation|null
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.tenant', 't')
            ->where('b.transferId = :transferId')
            ->andWhere('b.state = :completed')
            ->andWhere('t.id = :tenantId')
            ->setParameter('transferId', $transferId)
            ->setParameter('completed', 'COMPLETED')
            ->setParameter('tenantId', $userId)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int $limit
     * @param int|null $companyId
     * @return BalanceOperation[]
     */
    public function getRecentTransactions(int $limit = 5, int $companyId = null): array
    {
        $dql = $this->createQueryBuilder('b')
            ->leftJoin('b.tenant', 't')
            ->leftJoin('t.client', 'c');
        if (!is_null($companyId)) {
            $dql->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return $dql->setMaxResults($limit)->orderBy('b.id', 'DESC')->getQuery()->execute();
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
