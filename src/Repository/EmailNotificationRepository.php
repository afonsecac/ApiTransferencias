<?php

namespace App\Repository;

use App\Entity\EmailNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Mime\Email;

/**
 * @extends ServiceEntityRepository<EmailNotification>
 */
class EmailNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailNotification::class);
    }

    public function getLastNotification(int $accountId): ?EmailNotification
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.account', 'a')
            ->where('a.id = :accountId')
            ->setParameter('accountId', $accountId)
            ->andWhere('e.closedAt IS NULL')
            ->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }
    //    /**
    //     * @return EmailNotification[] Returns an array of EmailNotification objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?EmailNotification
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
