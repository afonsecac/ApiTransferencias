<?php

namespace App\Repository;

use App\Entity\ConfigureSequence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfigureSequence>
 *
 * @method ConfigureSequence|null find($id, $lockMode = null, $lockVersion = null)
 * @method ConfigureSequence|null findOneBy(array $criteria, array $orderBy = null)
 * @method ConfigureSequence[]    findAll()
 * @method ConfigureSequence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConfigureSequenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfigureSequence::class);
    }
}
