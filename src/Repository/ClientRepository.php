<?php

namespace App\Repository;

use App\Entity\Client;
use App\EntityPaginator\PaginatorResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @param array $filters
     * @param array $orderBy
     * @return PaginatorResponse
     */
    public function getAllClients(array $filters = [], array $orderBy = []): PaginatorResponse
    {
        $limit = $filters['limit'] ?? 10;
        $page = $filters['page'] ?? 0;
        $firstResult = $limit * $page;
        $dql = $this->createQueryBuilder('c');


        $dql->setFirstResult($firstResult)
            ->setMaxResults($limit);
        if (count($orderBy) === 0) {
            $dql->orderBy('c.companyName', 'ASC');
        } else {
            $dql->orderBy('c.'.$orderBy['orderBy'], strtoupper($orderBy['direction']));
        }
        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);
        return new PaginatorResponse($page, $limit, $total, $paginator->getQuery()->getArrayResult());
    }

}
