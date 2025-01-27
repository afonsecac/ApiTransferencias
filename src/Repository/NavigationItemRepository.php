<?php

namespace App\Repository;

use App\Entity\NavigationItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NavigationItem>
 */
class NavigationItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NavigationItem::class);
    }

    /**
     * @return NavigationItem[]
     */
    public function getNavigationItems(): array
    {
        return $this->createQueryBuilder('ni')
            ->select('ni')
            ->leftJoin('ni.children', 'c')
//            ->addSelect('c')
            ->where('ni.parent IS NULL AND c.parent IS NOT NULL')
            ->andWhere('ni.active = :is_active')
            ->setParameter('is_active', true)
            ->orderBy('ni.orderValue', 'ASC')
            ->addOrderBy('ni.title', 'ASC')
            ->getQuery()
            ->getResult();

    }

    public function getNavigationByUserAndClient(array $roles, int $clientId = null, int $userId = null): array
    {
        $dql = $this->createQueryBuilder('ni')
            ->select('ni')
            ->leftJoin('ni.children', 'c')
            ->leftJoin('ni.userPermissions', 'up')
            ->where('ni.parent IS NULL AND c.parent IS NOT NULL')
            ->andWhere('ni.active = :is_active')
            ->andWhere('up.isActive = :is_active')
            ->setParameter('is_active', true);

        return $this->extracted($clientId, $dql, $userId, $roles)->orderBy('ni.orderValue', 'ASC')
            ->addOrderBy('c.orderValue', 'ASC')
            ->addOrderBy('ni.title', 'ASC')
            ->addOrderBy('c.title', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * @param array $roles
     * @param int|null $clientId
     * @param int|null $userId
     * @return array
     */
    public function accessIds(array $roles, int $clientId = null, int $userId = null): array
    {
        $dql = $this->createQueryBuilder('ni')
            ->select('ni.id as parentId, c.id as childId')
            ->leftJoin('ni.children', 'c')
            ->leftJoin('ni.userPermissions', 'up')
            ->where('ni.parent IS NULL AND c.parent IS NOT NULL')
            ->andWhere('ni.active = :is_active')
            ->andWhere('up.isActive = :is_active')
            ->setParameter('is_active', true);

        return $this->extracted($clientId, $dql, $userId, $roles)->getQuery()->getScalarResult();
    }

    //    /**
    //     * @return NavigationItem[] Returns an array of NavigationItem objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('n.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?NavigationItem
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    /**
     * @param int|null $clientId
     * @param \Doctrine\ORM\QueryBuilder $dql
     * @param int|null $userId
     * @param array $roles
     * @return mixed
     */
    public function extracted(?int $clientId, \Doctrine\ORM\QueryBuilder $dql, ?int $userId, array $roles): mixed
    {
        if (!is_null($clientId)) {
            $dql->leftJoin('up.client', 'cl')
                ->andWhere('cl.id = :clientId OR up.minRoleRequired IN (:roles)')
                ->setParameter('clientId', $clientId);
        }
        if (!is_null($userId)) {
            $dql->leftJoin('up.userInfo', 'u')
                ->andWhere('u.id = :userId OR up.minRoleRequired IN (:roles)')
                ->setParameter('userId', $userId);
        } else {
            $dql->andWhere('up.minRoleRequired IN (:roles)');
        }
        return $dql->setParameter('roles', $roles);
    }
}
