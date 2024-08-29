<?php

namespace App\Repository;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSaleInfo>
 *
 * @method CommunicationSaleInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSaleInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSaleInfo[]    findAll()
 * @method CommunicationSaleInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSaleInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSaleInfo::class);
    }

    public function getLastPending(): array
    {
        $currentDate = (new \DateTimeImmutable('now'))->modify('-5 seconds');
        return $this->createQueryBuilder('csi')
            ->where('csi.createdAt <= :currentDate')
            ->andWhere('csi.updatedAt <= :currentDate')
            ->andWhere('csi.status = :status')
            ->setParameter('currentDate', $currentDate)
            ->setParameter('status', CommunicationStateEnum::PENDING)
            ->getQuery()->getResult();
    }

//    /**
//     * @return CommunicationSaleInfo[] Returns an array of CommunicationSaleInfo objects
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

//    public function findOneBySomeField($value): ?CommunicationSaleInfo
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
