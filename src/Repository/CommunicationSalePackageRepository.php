<?php

namespace App\Repository;

use App\Entity\CommunicationSalePackage;
use App\Enums\CommunicationStateEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSalePackage>
 *
 * @method CommunicationSalePackage|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSalePackage|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSalePackage[]    findAll()
 * @method CommunicationSalePackage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSalePackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSalePackage::class);
    }
}
