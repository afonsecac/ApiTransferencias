<?php

namespace App\Repository;

use App\Entity\ProductCommBenefits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCommBenefits>
 *
 * @method ProductCommBenefits|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductCommBenefits|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductCommBenefits[]    findAll()
 * @method ProductCommBenefits[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCommBenefitsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCommBenefits::class);
    }

//    /**
//     * @return ProductCommBenefits[] Returns an array of ProductCommBenefits objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ProductCommBenefits
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
