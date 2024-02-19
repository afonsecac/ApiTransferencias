<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPackage>
 *
 * @method CommunicationPackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPackage[]    findAll()
 * @method CommunicationPackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPackage::class);
    }

    /**
     * @throws Exception
     */
    public function deleteAllPackageByClient(int $clientId, int $envId, int $comId): int|string
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'UPDATE communication_package c SET  is_enabled = false WHERE environment_id = :env AND c.tenant_id = :client AND c.com_id = :comId AND c.is_enabled = :enabled';

        $info = $conn->executeStatement($sql, ['env' => $envId, 'client' => $clientId, 'comId' => $comId, 'enabled' => true]);

        return $info;
    }

    public function getPackageById(int $packageId, Account $account): CommunicationPackage | null
    {
        $currentDate = new \DateTimeImmutable('now');
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tenant', 'a')
            ->where('p.id = :id')
            ->andWhere('a.id = :aId')
            ->andWhere('p.isEnabled = :enabled')
            ->andWhere('p.startAt <= :currentDate AND p.endDateAt > :currentDate')
            ->setParameters([
                'id' => $packageId,
                'aId' => $account->getId(),
                'enabled' => true,
                'currentDate' => $currentDate
            ])
            ->getQuery()->getSingleResult();

    }
}
