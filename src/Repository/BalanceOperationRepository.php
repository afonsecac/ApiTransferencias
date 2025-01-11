<?php

namespace App\Repository;

use App\DTO\PaginationResult;
use App\Entity\BalanceOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * @param int $clientId
     * @return array
     */
    public function getBalancesInEnvironments(int $clientId): array
    {
        return $this->createQueryBuilder('bo')
            ->leftJoin('bo.tenant', 't')
            ->leftJoin('t.environment', 'e')
            ->leftJoin('t.client', 'c')
            ->select('SUM(bo.totalAmount) as currentBalance')
            ->addSelect('bo.totalCurrency as currency')
            ->addSelect('t.minBalance as minimumBalance')
            ->addSelect('t.criticalBalance as criticalBalance')
            ->addSelect('e.providerName as env')
            ->addSelect('e.id as id')
            ->where('c.id = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('e.providerName', 'ASC')
            ->groupBy('bo.totalCurrency')
            ->addGroupBy('e.providerName')
            ->addGroupBy('t.minBalance')
            ->addGroupBy('t.criticalBalance')
            ->addGroupBy('e.id')
            ->getQuery()->getScalarResult();
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

    /**
     * @param array $filters
     * @param string $orderBy
     * @param int $page
     * @param int $limit
     * @param int|null $companyId
     * @return \App\DTO\PaginationResult
     */
    public function getAllBalance(array $filters = [], string $orderBy = 'createdAt DESC', int $page = 0, int $limit = 10, int $companyId = null): PaginationResult
    {
        $dql = $this->createQueryBuilder('b')
            ->leftJoin('b.tenant', 't')
            ->leftJoin('t.client', 'c');
        $orderSplit = explode(' ', $orderBy);
        $orderField = $orderSplit[0];
        $orderSort = $orderSplit[1] ?? 'ASC';
        if (!is_null($companyId)) {
            $dql->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }
        $dql->setMaxResults($limit)
            ->setFirstResult($page * $limit)
            ->orderBy(sprintf('b.%s', $orderField), $orderSort);

        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);
        return new PaginationResult($total, $page, $limit, $paginator->getQuery()->execute());
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
