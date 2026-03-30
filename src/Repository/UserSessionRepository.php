<?php

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    public function getActiveUserSession(int $userId): ?UserSession
    {
        $currentDate = new \DateTimeImmutable('now');

        return $this->createQueryBuilder('us')
            ->leftJoin('us.userBySession', 'u')
            ->andWhere('u.id = :userId')
            ->andWhere('us.closedAt IS NULL')
            ->andWhere('us.createdAt <= :currentDate')
            ->setParameter('userId', $userId)
            ->setParameter('currentDate', $currentDate)
            ->orderBy('us.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int $timeInMinutes
     * @return UserSession[]
     */
    public function sessionUnclosed(int $timeInMinutes): array
    {
        $currentDate = new \DateTimeImmutable('now');
        return $this->createQueryBuilder('us')
            ->where('us.closedAt IS NULL')
            ->andWhere('us.lastAccessAt <= :currentDate')
            ->setParameter('currentDate', $currentDate->modify('-'.$timeInMinutes.' minutes'))
            ->getQuery()->execute();
    }

    /**
     * @param int $userId
     * @return UserSession[]
     */
    public function sessionUnclosedByUser(int $userId): array
    {
        return $this->createQueryBuilder('us')
            ->leftJoin('us.userBySession', 'u')
            ->where('us.closedAt IS NULL')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()->execute();
    }

}
