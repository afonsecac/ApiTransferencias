<?php

namespace App\Repository;


use App\Entity\CommunicationProduct;
use App\EntityPaginator\PaginatorResponse;
use App\Util\IPaginationResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationProduct>
 *
 * @method CommunicationProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationProduct[]    findAll()
 * @method CommunicationProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationProduct::class);
    }

    /**
     * @param int $page
     * @param int $limit
     * @param string $env
     * @param string|null $query
     * @return \App\Util\IPaginationResponse
     */
    public function getProducts(
        int $page = 0,
        int $limit = 10,
        string $env = 'TEST',
        string $query = null
    ): IPaginationResponse {
        $currentDate = new \DateTimeImmutable();
        $dql = $this->createQueryBuilder('p')
            ->select('p')
            ->leftJoin('p.environment', 'e')
            ->where('e.type = :env')
            ->andWhere('p.endDateAt IS NULL OR p.endDateAt > :currentDate')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('enabled', true)
            ->setParameter('currentDate', $currentDate)
            ->setParameter('env', $env);
        if (!is_null($query)) {
            $dql->andWhere('p.description LIKE :query')
                ->setParameter('query', str_replace('*', '%', $query));
        }
        $dql->setMaxResults($limit)
            ->setFirstResult($page * $limit)
            ->orderBy('p.price')
            ->addOrderBy('p.packageId')
            ->getQuery();
        $paginator = new Paginator($dql, fetchJoinCollection: false);
        $total = count($paginator);
        return new PaginatorResponse($page, $limit, $total, $paginator->getQuery()->getArrayResult());
    }

    /**
     * @param int $packageId
     * @param int $envId
     * @return CommunicationProduct[]
     */
    public function getProductsByPackageId(int $packageId, int $envId): array
    {
        $currentDate = new \DateTimeImmutable('now');
        $sql = $this->createQueryBuilder('c')
            ->leftJoin('c.environment', 'e')
            ->where('c.packageId = :cPackageId')
            ->andWhere('c.initialDate <= :currentDate')
            ->andWhere('c.endDateAt > :currentDate')
            ->andWhere('c.enabled = :enabled')
            ->andWhere('e.id = :env')
            ->setParameters([
                'cPackageId' => $packageId,
                'currentDate' => $currentDate,
                'enabled' => true,
                'env' => $envId,
            ]);

        return $sql->getQuery()->getResult();
    }
}
