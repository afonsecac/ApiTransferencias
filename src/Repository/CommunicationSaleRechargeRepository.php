<?php

namespace App\Repository;

use App\Entity\CommunicationSaleRecharge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSaleRecharge>
 *
 * @method CommunicationSaleRecharge|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSaleRecharge|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSaleRecharge[]    findAll()
 * @method CommunicationSaleRecharge[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSaleRechargeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSaleRecharge::class);
    }

//    /**
//     * @return CommunicationSaleRecharge[] Returns an array of CommunicationSaleRecharge objects
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

//    public function findOneBySomeField($value): ?CommunicationSaleRecharge
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
