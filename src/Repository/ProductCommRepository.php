<?php

namespace App\Repository;

use App\Entity\ProductComm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductComm>
 *
 * @method ProductComm|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductComm|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductComm[]    findAll()
 * @method ProductComm[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCommRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductComm::class);
    }

}
