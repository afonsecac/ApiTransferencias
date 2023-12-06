<?php

namespace App\Repository;

use App\Entity\CommunicationOffice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationOffice>
 *
 * @method CommunicationOffice|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationOffice|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationOffice[]    findAll()
 * @method CommunicationOffice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationOfficeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationOffice::class);
    }

//    /**
//     * @return CommunicationOffice[] Returns an array of CommunicationOffice objects
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

//    public function findOneBySomeField($value): ?CommunicationOffice
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
