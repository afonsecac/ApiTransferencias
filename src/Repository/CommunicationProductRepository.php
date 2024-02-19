<?php

namespace App\Repository;

use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationProduct>
 *
 * @method CommunicationProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationProduct[]    findAll()
 * @method CommunicationProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationProduct::class);
    }

    /**
     * @param int $packageId
     * @param int $envId
     * @return CommunicationProduct[]
     */
    public function getProductsByPackageId(int $packageId, int $envId): array
    {
        $currentDate = new \DateTimeImmutable('now');
        $sql = $this->createQueryBuilder('c')
            ->leftJoin('c.environment', 'e')
            ->where('c.packageId = :cPackageId')
            ->andWhere('c.initialDate <= :currentDate')
            ->andWhere('c.endDateAt > :currentDate')
            ->andWhere('c.enabled = :enabled')
            ->andWhere('e.id = :env')
            ->setParameters([
                'cPackageId' => $packageId,
                'currentDate' => $currentDate,
                'enabled' => true,
                'env' => $envId
            ]);

        return $sql->getQuery()->getResult();
    }
}
