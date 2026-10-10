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
     * @param int|null $clientId filtra por App\Entity\ReportMarked::$client (null = sin filtro)
     * @param int $page
     * @param int $limit
     * @return \App\DTO\PaginationResult
     */
    public function list(?int $clientId = null, int $page = 0, int $limit = 40): PaginationResult
    {
        $dql = $this->createQueryBuilder('r');
        if (!is_null($clientId)) {
            $dql
                ->andWhere('r.client = :clientId')
                ->setParameter('clientId', $clientId);
        }
        $dql->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($page * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);

        return new PaginationResult($total, $page, $limit, $paginator->getQuery()->execute());
    }

}
