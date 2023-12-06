<?php

namespace App\Repository;

use App\Entity\CommunicationSale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSale>
 *
 * @method CommunicationSale|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSale|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSale[]    findAll()
 * @method CommunicationSale[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSale::class);
    }

//    /**
//     * @return CommunicationSale[] Returns an array of CommunicationSale objects
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

//    public function findOneBySomeField($value): ?CommunicationSale
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * @throws NonUniqueResultException
     */
    public function getSequence(): CommunicationSale|null {
        return $this->createQueryBuilder('a')
            ->setMaxResults(1)
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
