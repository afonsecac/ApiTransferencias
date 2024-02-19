<?php

namespace App\Repository;

use App\Entity\CommunicationOffice;
use App\Entity\Environment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationOffice>
 *
 * @method CommunicationOffice|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationOffice|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationOffice[]    findAll()
 * @method CommunicationOffice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationOfficeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationOffice::class);
    }

    /**
     * @throws Exception
     */
    public function deleteAll(Environment $environment, int $provinceId): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'DELETE FROM communication_office c WHERE c.province_id = :prov AND c.environment_id = :env';

        $conn->executeStatement($sql, ['prov' => $provinceId, 'env' => $environment->getId()]);
    }
}
