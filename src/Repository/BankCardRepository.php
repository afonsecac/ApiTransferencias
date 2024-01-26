<?php

namespace App\Repository;

use App\Entity\BankCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
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

    /**
     * @throws NonUniqueResultException
     */
    public function getBeneficiaryCard(int $id): BankCard | null
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.beneficiary', 'a')
            ->where('b.id = :bId OR a.id = :bId')
            ->andWhere('a.isActive = :aIsActive')
            ->setMaxResults(1)
            ->setParameters([
                'bId' => $id,
                'aIsActive' => true
            ])
            ->getQuery()->getOneOrNullResult();
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
