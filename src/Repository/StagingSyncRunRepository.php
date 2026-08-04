<?php

namespace App\Repository;

use App\Entity\StagingSyncRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StagingSyncRun>
 *
 * @method StagingSyncRun|null find($id, $lockMode = null, $lockVersion = null)
 * @method StagingSyncRun|null findOneBy(array $criteria, array $orderBy = null)
 * @method StagingSyncRun[]    findAll()
 * @method StagingSyncRun[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StagingSyncRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StagingSyncRun::class);
    }

    public function findLatest(): ?StagingSyncRun
    {
        return $this->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * @return StagingSyncRun[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->findBy([], ['id' => 'DESC'], $limit);
    }
}
