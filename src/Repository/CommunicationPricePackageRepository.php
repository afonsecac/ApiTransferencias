<?php

namespace App\Repository;

use App\Entity\CommunicationPricePackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPricePackage>
 *
 * @method CommunicationPricePackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPricePackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPricePackage[]    findAll()
 * @method CommunicationPricePackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPricePackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPricePackage::class);
    }

    /**
     * @param int $productId
     * @return array
     */
    public function getIdsWithPrices(int $productId): array
    {
        $currentDate = new \DateTimeImmutable();
        return $this->createQueryBuilder('p')
            ->leftJoin('p.product', 'pd')
            ->leftJoin('p.priceUsed', 'u')
            ->select('u.id')
            ->where('pd.id = :pdId')
            ->andWhere('pd.enabled = :enabled')
            ->andWhere('p.isActive = :enabled')
            ->andWhere('u.isActive = :enabled')
            ->andWhere('u.validStartAt <= :currentDate AND (u.validEndAt > :currentDate OR u.validEndAt IS NULL)')
            ->andWhere('pd.initialDate <= :currentDate AND (pd.endDateAt > :currentDate OR pd.endDate IS NULL)')
            ->andWhere('p.activeStartAt <= :currentDate AND (p.activeEndAt > :currentDate OR p.activeEndAt IS NULL)')
            ->setParameters([
                'pdId' => $productId,
                'enabled' => true,
                'currentDate' => $currentDate,
            ])
            ->getQuery()->getScalarResult();
    }
}
