<?php

namespace App\Repository;

use App\DTO\PaginationResult;
use App\Entity\ReportMarked;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportMarked>
 */
class ReportMarkedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportMarked::class);
    }

    /**
     * @param int|null $accountId
     * @param int $limit
     * @param int $page
     * @return \App\DTO\PaginationResult
     */
    public function list(?int $accountId = null, int $limit = 10, int $page = 0): PaginationResult
    {
        $dql = $this->createQueryBuilder('r');
        if (!is_null($accountId)) {
            $dql
                ->leftJoin('r.account', 't')
                ->leftJoin('t.client', 'c')
                ->andWhere('c.id = :accountId')
                ->setParameter('accountId', $accountId);
        }
        $dql->orderBy('r.createdAt', 'DESC');
        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);

        return new PaginationResult($total, $page, $limit, $paginator->getQuery()->execute());
    }

}
