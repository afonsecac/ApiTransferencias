<?php

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 *
 * @method Account|null find($id, $lockMode = null, $lockVersion = null)
 * @method Account|null findOneBy(array $criteria, array $orderBy = null)
 * @method Account[]    findAll()
 * @method Account[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function getClientsWithCondition(bool $isActive = true, string $env = 'TEST'): array
    {
        $currentDate = new \DateTimeImmutable();

        return $this->createQueryBuilder('a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('a.environment', 'e')
            ->where('c.isActive = :isActive AND c.isActive = :isActive')
            ->andWhere('c.isActiveAt <= :currentDate')
            ->andWhere('c.removeAt IS NULL')
            ->andWhere('a.isActiveAt <= :currentDate')
            ->andWhere('e.type = :env')
            ->andWhere('e.isActive = :isEnvActive')
            ->setParameters(
                new ArrayCollection([
                    new Parameter('isActive', $isActive),
                    new Parameter('currentDate', $currentDate),
                    new Parameter('env', $env),
                    new Parameter('isEnvActive', true),
                ])
            )->getQuery()->execute();
    }

    /**
     * @return Account[]
     */
    public function getAccounts(): array
    {
        return $this->createQueryBuilder('a')->leftJoin('a.client', 'c')
            ->orderBy('c.companyName')->getQuery()->execute();
    }
//    /**
//     * @return Account[] Returns an array of Account objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Account
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
