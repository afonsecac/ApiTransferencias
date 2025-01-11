<?php

namespace App\Repository;

use App\Entity\UserCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserCode>
 */
class UserCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCode::class);
    }

    /**
     * @param string $email
     * @param string $code
     * @return \App\Entity\UserCode|null
     */
    public function getByCodeAndEmailNotValidated(string $email, string $code): ?UserCode
    {
        return $this->createQueryBuilder('uc')
            ->leftJoin('uc.user', 'u')
            ->where('uc.code = :code')
            ->andWhere('u.email = :email')
            ->andWhere('uc.usedAt IS NULL')
            ->andWhere('uc.emailValidated IS NULL OR uc.emailValidated = :validated')
            ->andWhere('uc.invalidAt IS NULL OR uc.invalidAt > :currentDate')
            ->setParameters(
                new ArrayCollection([
                    'code' => $code,
                    'email' => $email,
                    'currentDate' => new \DateTimeImmutable('now'),
                    'validated' => false,
                ])
            )
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * @param string $email
     * @return \App\Entity\UserCode|null
     */
    public function getLastCodeByEmail(string $email): ?UserCode
    {
        return $this->createQueryBuilder('uc')
            ->leftJoin('uc.user', 'u')
            ->where('u.email = :email')
            ->andWhere('uc.usedAt IS NULL')
            ->andWhere('uc.invalidAt IS NULL OR uc.invalidAt > :currentDate')
            ->setParameters(new ArrayCollection([
                'email' => $email,
                'currentDate' => new \DateTimeImmutable('now'),
            ]))
            ->orderBy('uc.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * @param string $code
     * @param string $email
     * @return \App\Entity\UserCode|null
     */
    public function getByCodeAndEmailNotUsed(string $code, string $email): ?UserCode
    {
        return $this->createQueryBuilder('uc')
            ->leftJoin('uc.user', 'u')
            ->where('uc.code = :code')
            ->andWhere('u.email = :email')
            ->andWhere('uc.usedAt IS NULL')
            ->andWhere('uc.invalidAt IS NULL OR uc.invalidAt > :currentDate')
            ->setParameters(
                new ArrayCollection([
                    'code' => $code,
                    'email' => $email,
                    'currentDate' => new \DateTimeImmutable('now'),
                ])
            )
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return UserCode[] Returns an array of UserCode objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserCode
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
