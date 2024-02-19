<?php

namespace App\Repository;

use App\Entity\CommunicationNationality;
use App\Entity\Environment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationNationality>
 *
 * @method CommunicationNationality|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationNationality|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationNationality[]    findAll()
 * @method CommunicationNationality[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationNationalityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationNationality::class);
    }

    /**
     * @throws Exception
     */
    public function deleteAll(Environment $environment)
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'DELETE FROM communication_nationality c WHERE c.environment_id = :env';

        return $conn->executeQuery($sql, ['env' => $environment->getId()])->fetchAssociative();
    }
}
