<?php

namespace App\Repository;

use App\Entity\EnvAuth;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnvAuth>
 *
 * @method EnvAuth|null find($id, $lockMode = null, $lockVersion = null)
 * @method EnvAuth|null findOneBy(array $criteria, array $orderBy = null)
 * @method EnvAuth[]    findAll()
 * @method EnvAuth[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EnvAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnvAuth::class);
    }

}
