<?php

namespace App\Repository;

use App\Entity\CommunicationPriceTable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPriceTable>
 *
 * @method CommunicationPriceTable|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPriceTable|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPriceTable[]    findAll()
 * @method CommunicationPriceTable[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPriceTableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPriceTable::class);
    }

//    /**
//     * @return CommunicationPriceTable[] Returns an array of CommunicationPriceTable objects
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

//    public function findOneBySomeField($value): ?CommunicationPriceTable
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
