<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
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
    public function getPackageById(int $packageId, Account $account): CommunicationClientPackage|null
    {
        $currentDate = new \DateTimeImmutable('now');

        return $this->createQueryBuilder('p')
            ->leftJoin('p.tenant', 'a')
            ->where('p.id = :id')
            ->andWhere('a.id = :aId')
            ->andWhere('p.activeStartAt <= :currentDate AND p.activeEndAt > :currentDate')
            ->setParameters(new ArrayCollection([
                new Parameter('id', $packageId),
                new Parameter('aId', $account->getId()),
                new Parameter('currentDate', $currentDate),
            ]))
            ->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    /**
     * @param string $env
     * @param int|null $tenant
     * @return CommunicationClientPackage[]
     */
    public function getAllPackages(string $env = 'TEST', int $tenant = null): array
    {
        $currentDate = new \DateTimeImmutable('now');
        $dql = $this->createQueryBuilder('p')
            ->leftJoin('p.tenant', 't')
            ->leftJoin('p.priceClientPackage', 'pc')
            ->leftJoin('t.client', 'c')
            ->leftJoin('pc.product', 'pe')
            ->leftJoin('pe.environment', 'e')
            ->select('p')
            ->addSelect('t')
            ->addSelect('c')
            ->where('p.activeStartAt <= :currentDate AND p.activeEndAt > :currentDate')
            ->andWhere('e.type = :type')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('type', $env);

        if (!is_null($tenant) && $tenant) {
            $dql->andWhere('t.id = :tenant')
                ->andWhere('t.isActive = :isActive')
                ->setParameter('tenant', $tenant)
                ->setParameter('isActive', true);
        }

        return $dql->orderBy('c.companyName')->addOrderBy('p.amount')->getQuery()->getResult();
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
