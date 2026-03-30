<?php

namespace App\Repository;

use App\Entity\UserPassword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPassword>
 *
 * @method UserPassword|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserPassword|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserPassword[]    findAll()
 * @method UserPassword[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserPasswordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPassword::class);
    }

}
