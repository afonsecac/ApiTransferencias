<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationClientPackage>
 *
 * @method CommunicationClientPackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationClientPackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationClientPackage[]    findAll()
 * @method CommunicationClientPackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationClientPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationClientPackage::class);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getPackageById(int $packageId, Account $account): CommunicationClientPackage | null
    {
        $currentDate = new \DateTimeImmutable('now');
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tenant', 'a')
            ->where('p.id = :id')
            ->andWhere('a.id = :aId')
            ->andWhere('p.activeStartAt <= :currentDate AND p.activeEndAt > :currentDate')
            ->setParameters([
                'id' => $packageId,
                'aId' => $account->getId(),
                'currentDate' => $currentDate
            ])
            ->getQuery()->getSingleResult();
    }

//    /**
//     * @return CommunicationClientPackage[] Returns an array of CommunicationClientPackage objects
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

//    public function findOneBySomeField($value): ?CommunicationClientPackage
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
