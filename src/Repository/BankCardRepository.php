<?php

namespace App\Repository;

use App\Entity\BankCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BankCard>
 *
 * @method BankCard|null find($id, $lockMode = null, $lockVersion = null)
 * @method BankCard|null findOneBy(array $criteria, array $orderBy = null)
 * @method BankCard[]    findAll()
 * @method BankCard[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BankCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankCard::class);
    }

//    /**
//     * @return BankCard[] Returns an array of BankCard objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?BankCard
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
