<?php

namespace App\Repository;

use App\Entity\CommunicationSalePackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSalePackage>
 *
 * @method CommunicationSalePackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSalePackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSalePackage[]    findAll()
 * @method CommunicationSalePackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSalePackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSalePackage::class);
    }

//    /**
//     * @return CommunicationSalePackage[] Returns an array of CommunicationSalePackage objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?CommunicationSalePackage
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
