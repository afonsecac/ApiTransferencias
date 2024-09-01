<?php

namespace App\Repository;

use App\Entity\CommunicationPromotions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPromotions>
 *
 * @method CommunicationPromotions|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPromotions|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPromotions[]    findAll()
 * @method CommunicationPromotions[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPromotionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPromotions::class);
    }

    /**
     * @param int $promotionId
     * @return \App\Entity\CommunicationPromotions|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getActivePromotionById(int $promotionId): ?CommunicationPromotions
    {
        $currentDate = new \DateTimeImmutable();
        return $this->createQueryBuilder('p')
            ->leftJoin('p.product', 'pc')
            ->where('p.id = :promotionId')
            ->andWhere('p.startAt <= :currentDate AND p.endAt > :currentDate')
            ->andWhere('pc.initialDate <= :currentDate AND pc.endDateAt > :currentDate')
            ->andWhere('pc.enabled = :enabled')
            ->setParameters(new ArrayCollection([
                new Parameter('promotionId', $promotionId),
                new Parameter('currentDate', $currentDate),
                new Parameter('enabled', true),
            ]))->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int $promotionId
     * @param int $packageId
     * @return \App\Entity\CommunicationPromotions|null
     */
    public function getFuturePromotionById(int $promotionId, int $packageId): ?CommunicationPromotions
    {
        $currentDate = new \DateTimeImmutable('now');
        return $this->createQueryBuilder('p')
            ->leftJoin('p.products', 'cp')
            ->where('p.id = :promotionId')
            ->andWhere('cp.id = :packageId')
            ->andWhere('p.startAt > :currentDate')
            ->andWhere('p.createdAt <= :currentDate')
            ->andWhere('p.updatedAt <= :currentDate')
            ->setParameters(new ArrayCollection([
                new Parameter('promotionId', $promotionId),
                new Parameter('currentDate', $currentDate),
                new Parameter('packageId', $packageId),
            ]))
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

//    /**
//     * @return CommunicationPromotions[] Returns an array of CommunicationPromotions objects
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

//    public function findOneBySomeField($value): ?CommunicationPromotions
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
