<?php

namespace App\Repository;

use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSaleRecharge>
 *
 * @method CommunicationSaleRecharge|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSaleRecharge|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSaleRecharge[]    findAll()
 * @method CommunicationSaleRecharge[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSaleRechargeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSaleRecharge::class);
    }

    /**
     * @return CommunicationSalePackage[]
     */
    public function getCurrentActivePromotionsReserves(): array
    {
        $currentDate = new \DateTimeImmutable('now');
        return $this->createQueryBuilder('r')
            ->leftJoin('r.promotion', 'p')
            ->leftJoin('r.package', 'q')
            ->where('p.createdAt < :currentDate')
            ->andWhere('p.startAt <= :currentDate')
            ->andWhere('p.endAt >= :currentDate')
            ->andWhere('r.state = :state')
            ->andWhere('q.activeEndAt > :currentDate')
            ->setParameters(new ArrayCollection([
                new Parameter('currentDate', $currentDate),
                new Parameter('state', CommunicationStateEnum::RESERVED),
            ]))->getQuery()->execute();
    }
}
