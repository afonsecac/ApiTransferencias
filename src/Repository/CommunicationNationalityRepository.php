<?php

namespace App\Repository;

use App\Entity\CommunicationNationality;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationNationality>
 *
 * @method CommunicationNationality|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationNationality|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationNationality[]    findAll()
 * @method CommunicationNationality[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationNationalityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationNationality::class);
    }

//    /**
//     * @return CommunicationNationality[] Returns an array of CommunicationNationality objects
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

//    public function findOneBySomeField($value): ?CommunicationNationality
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
