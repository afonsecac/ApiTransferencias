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
     * @return CommunicationSaleRecharge[]
     */
    public function getCurrentActivePromotionsReserves(): array
    {
        $now = new \DateTimeImmutable('now');
        // Esperar al menos 1 minuto tras el inicio de la promoción antes de enviar al proveedor
        $activationThreshold = $now->modify('-1 minute');

        return $this->createQueryBuilder('r')
            ->join('r.promotion', 'p')
            ->join('r.package', 'q')
            ->where('r.state = :state')
            ->andWhere('r.stateProcess = :stateProcess')
            ->andWhere('p.startAt <= :activationThreshold')
            ->andWhere('p.endAt >= :now')
            ->andWhere('q.activeEndAt > :now')
            ->setParameters(new ArrayCollection([
                new Parameter('now', $now),
                new Parameter('activationThreshold', $activationThreshold),
                new Parameter('state', CommunicationStateEnum::RESERVED),
                new Parameter('stateProcess', CommunicationStateEnum::CREATED->value),
            ]))->getQuery()->execute();
    }
}
