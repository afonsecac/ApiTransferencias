<?php

namespace App\Repository;

use App\Entity\Sender;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sender>
 *
 * @method Sender|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sender|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sender[]    findAll()
 * @method Sender[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SenderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sender::class);
    }

}
