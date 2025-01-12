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
    public function getNavigationItems(): array {
        return $this->createQueryBuilder('ni')
            ->select('ni')
            ->leftJoin('ni.children', 'c')
            ->addSelect('c')
            ->where('ni.parent IS NULL OR c.parent IS NOT NULL')
            ->andWhere('ni.active = :is_active')
            ->setParameter('is_active', true)
            ->orderBy('ni.orderValue', 'ASC')
            ->addOrderBy('ni.title', 'ASC')
            ->getQuery()
            ->getResult();

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
}
