<?php

namespace App\Repository;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationSaleInfo>
 *
 * @method CommunicationSaleInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationSaleInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationSaleInfo[]    findAll()
 * @method CommunicationSaleInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationSaleInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationSaleInfo::class);
    }

    /**
     * @return CommunicationSaleInfo[]
     * @throws \DateMalformedStringException
     */
    public function getLastPending(): array
    {
        $currentDate = (new \DateTimeImmutable('now'))->modify('-5 seconds');
        return $this->createQueryBuilder('csi')
            ->andWhere('csi.state = :status')
            ->setParameter('status', CommunicationStateEnum::PENDING)
            ->getQuery()->execute();
    }
}
