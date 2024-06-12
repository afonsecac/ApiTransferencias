<?php

namespace App\Repository;

use App\Entity\CommunicationPromotions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPromotions>
 *
 * @method CommunicationPromotions|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPromotions|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPromotions[]    findAll()
 * @method CommunicationPromotions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPromotionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPromotions::class);
    }

//    /**
//     * @return CommunicationPromotions[] Returns an array of CommunicationPromotions objects
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

//    public function findOneBySomeField($value): ?CommunicationPromotions
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
