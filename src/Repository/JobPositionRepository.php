<?php

namespace App\Repository;

use App\Entity\JobPosition;
use App\Enums\JobPositionAreaEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobPosition>
 */
class JobPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobPosition::class);
    }

    /** @return JobPosition[] */
    public function findByArea(JobPositionAreaEnum $area): array
    {
        return $this->createQueryBuilder('jp')
            ->where('jp.area = :area')
            ->andWhere('jp.isActive = true')
            ->setParameter('area', $area)
            ->getQuery()
            ->getResult();
    }
}
