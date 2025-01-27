<?php

namespace App\Repository;

use App\DTO\PaginationResult;
use App\Entity\BalanceOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
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

    public function getPreviousAmount(int $accountId): float | null
    {
        return $this->createQueryBuilder('b')
            ->select('SUM(b.totalAmount) as total')
            ->leftJoin('b.tenant', 't')
            ->where('t.id = :accountId')
            ->andWhere('b.markAsReported = :report')
            ->andWhere('b.state = :completed')
            ->setParameter('accountId', $accountId)
            ->setParameter('report', true)
            ->setParameter('completed', 'COMPLETED')
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int $accountId
     * @return BalanceOperation[]
     */
    public function filterNoMarkedTransactions(int $accountId): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.tenant', 't')
            ->leftJoin('b.communicationSale', 'cs')
            ->where('b.markAsReported = :report OR b.markAsReported IS NULL')
            ->andWhere('b.state = :completed')
            ->andWhere('t.id = :accountId')
            ->setParameters(new ArrayCollection([
                new Parameter('report', false),
                new Parameter('completed', 'COMPLETED'),
                new Parameter('accountId', $accountId),
            ]))
            ->orderBy('b.id', 'ASC')
            ->getQuery()->execute();
    }

    public function getLastMarkedAsReported(int $userId = null): ?BalanceOperation
    {
        $query = $this->createQueryBuilder('b')
            ->leftJoin('b.tenant', 't')
            ->where('b.markAsReported = :report')
            ->andWhere('b.state = :completed')
            ->setParameter('report', true)
            ->setParameter('completed', 'COMPLETED');
        if ($userId) {
            $query->andWhere('t.id = :userId')
                ->setParameter('userId', $userId);
        }
        $query->orderBy('b.id', 'DESC')
            ->setMaxResults(1);

        return $query->getQuery()->getOneOrNullResult();
    }

    public function getReportBalanceOperation(
        int $userId,
        bool $showAll = false,
        int $page = 0,
        int $limit = 20
    ): PaginationResult {
        $last = $this->getLastMarkedAsReported($userId);
        $dql = $this->createQueryBuilder('b')
            ->leftJoin('b.tenant', 't')
            ->leftJoin('t.client', 'c')
            ->leftJoin('b.communicationSale', 'cs')
            ->leftJoin('cs.package', 'p')
            ->where('b.markAsReported = :report')
            ->setParameter('report', false);
        if ($userId) {
            $dql->andWhere('t.id = :userId')
                ->setParameter('userId', $userId);
        }
        if (!is_null($last)) {
            $dql->andWhere('b.id > :lastId')->setParameter('lastId', $last->getId());
        }
        $dql->orderBy('b.id', 'ASC');
        if (!$showAll) {
            $dql->setMaxResults($limit)->setFirstResult($page * $limit);
        }
        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);

        return new PaginationResult($total, $page, $limit, $paginator->getQuery()->execute());
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
            ->andWhere('t.isActive = :isActive')
            ->andWhere('c.isActive = :isActive')
            ->setParameter('clientId', $clientId)
            ->setParameter('isActive', true)
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
     * @throws \DateMalformedStringException
     */
    public function getAllBalance(
        array $filters = [],
        string $orderBy = 'createdAt DESC',
        int $page = 0,
        int $limit = 10,
        int $companyId = null
    ): PaginationResult {
        $dql = $this->createQueryBuilder('b')
            ->leftJoin('b.tenant', 't')
            ->leftJoin('t.client', 'c')
            ->leftJoin('b.communicationSale', 'cs');
        $orderSplit = explode(' ', $orderBy);
        $orderField = $orderSplit[0];
        $orderSort = $orderSplit[1] ?? 'ASC';
        if (!is_null($companyId)) {
            $dql->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }
        if (count($filters) > 0) {
            $filterObject = (object)$filters;
            if (property_exists($filterObject, 'operationType') && !empty($filterObject->operationType)) {
                $dql->andWhere('b.operationType = :operationType')
                    ->setParameter('operationType', $filterObject->operationType);
            }
            if (property_exists($filterObject, 'state') && !empty($filterObject->state)) {
                $dql->andWhere('b.state = :state')
                    ->setParameter('state', $filterObject->state);
            }
            if (property_exists($filterObject, 'env') && !empty($filterObject->env)) {
                if (is_numeric($filterObject->env)) {
                    $dql->leftJoin('t.environment', 'e')
                        ->andWhere('e.id = :environment')
                        ->setParameter('environment', $filterObject->env);
                }
            }
            if (property_exists(
                    $filterObject,
                    'start'
                ) && !empty($filterObject->start) && $filterObject->start !== 'null') {
                $startDate = new \DateTimeImmutable($filterObject->start);
                $dql->andWhere('b.createdAt >= :start')->setParameter('start', $startDate);
            }
            if (property_exists($filterObject, 'end') && !empty($filterObject->end) && $filterObject->end !== 'null') {
                $endDate = new \DateTimeImmutable($filterObject->end);
                $dql->andWhere('b.createdAt <= :end')->setParameter('end', $endDate);
            }
        }
        $myOrderField = str_contains($orderField, '.') ? $orderField : sprintf('b.%s', $orderField);
        $dql->setMaxResults($limit)
            ->setFirstResult($page * $limit)
            ->orderBy($myOrderField, $orderSort)->addOrderBy('b.createdAt', 'DESC');

        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);

        return new PaginationResult($total, $page, $limit, $paginator->getQuery()->getResult());
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
