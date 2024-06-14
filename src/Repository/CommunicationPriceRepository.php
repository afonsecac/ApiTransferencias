<?php

namespace App\Repository;

use App\Entity\CommunicationPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPrice>
 *
 * @method CommunicationPrice|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPrice|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPrice[]    findAll()
 * @method CommunicationPrice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPrice::class);
    }

    /**
     * @param array $ids
     * @return array
     */
    public function getPricesNoUsed(array $ids): array
    {
        $dql = $this->createQueryBuilder('p');
        if (count($ids) !== 0) {
            $dql = $dql->where($dql->expr()->notIn('p.id', ':ids'))
                ->setParameter('ids', $ids);
        }

        return $dql->orderBy('p.amount', 'ASC')->getQuery()->getResult();
    }
}
