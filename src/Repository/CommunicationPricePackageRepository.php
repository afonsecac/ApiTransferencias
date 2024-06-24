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
     * @param int|null $clientId
     * @return array
     */
    public function getIdsWithPrices(int $productId, int $clientId = null): array
    {
        $currentDate = new \DateTimeImmutable();
        $dql = $this->createQueryBuilder('p')
            ->leftJoin('p.product', 'pd')
            ->leftJoin('p.priceUsed', 'u')
            ->select('u.id')
            ->where('pd.id = :pdId')
            ->andWhere('pd.enabled = :enabled')
            ->andWhere('p.isActive = :enabled')
            ->andWhere('u.isActive = :enabled')
            ->andWhere('u.validStartAt <= :currentDate AND (u.validEndAt > :currentDate OR u.validEndAt IS NULL)')
            ->andWhere('pd.initialDate <= :currentDate AND (pd.endDateAt > :currentDate OR pd.endDateAt IS NULL)')
            ->andWhere('p.activeStartAt <= :currentDate AND (p.activeEndAt > :currentDate OR p.activeEndAt IS NULL)')
            ->setParameters([
                'pdId' => $productId,
                'enabled' => true,
                'currentDate' => $currentDate,
            ]);
        if (!is_null($clientId)) {
            $dql = $dql->leftJoin('p.tenant', 't')
                ->andWhere('t.id = :client')
                ->setParameter('client', $clientId);
        } else {
            $dql = $dql->andWhere('p.tenant IS NULL');
        }

        return $dql->orderBy('p.price', 'ASC')
            ->getQuery()->getScalarResult();
    }

    public function getPricesByEnvironment(string $env = 'TEST', int $tenantId = null): array
    {
        $currentDate = new \DateTimeImmutable();
        $dql = $this->createQueryBuilder('pp')
            ->leftJoin('pp.product', 'p')
            ->leftJoin('p.environment', 'e')
            ->where('e.type = :type')
            ->andWhere('pp.activeStartAt <= :currentDate')
            ->andWhere('pp.activeEndAt > :currentDate')
            ->andWhere('p.initialDate <= :currentDate')
            ->andWhere('p.endDateAt > :currentDate')
            ->andWhere('p.enabled <= :enabled')
            ->setParameters([
                'currentDate' => $currentDate,
                'enabled' => true,
                'type' => $env,
            ]);
        if (!is_null($tenantId)) {
            $dql = $dql
                ->leftJoin('pp.tenant', 't')->andWhere('t.id = :tenant')
                ->setParameter('tenant', $tenantId);
        } else {
            $dql->andWhere('pp.tenant IS NULL');
        }

        return $dql->orderBy('pp.price', 'ASC')->getQuery()->getResult();
    }
}
