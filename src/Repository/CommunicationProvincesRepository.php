<?php

namespace App\Repository;

use App\Entity\CommunicationProvinces;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationProvinces>
 *
 * @method CommunicationProvinces|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationProvinces|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationProvinces[]    findAll()
 * @method CommunicationProvinces[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationProvincesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationProvinces::class);
    }
}
