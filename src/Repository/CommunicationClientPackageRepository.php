<?php

namespace App\Repository;

use App\Entity\CommunicationClientPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
