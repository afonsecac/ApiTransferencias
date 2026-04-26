<?php

namespace App\Repository;

use App\Entity\ProductCommBenefits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCommBenefits>
 *
 * @method ProductCommBenefits|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductCommBenefits|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductCommBenefits[]    findAll()
 * @method ProductCommBenefits[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductCommBenefitsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCommBenefits::class);
    }

}
