<?php

namespace App\Repository;

use App\Entity\CommunicationPromotionProviderProduct;
use App\Entity\CommunicationPromotions;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationPromotionProviderProduct>
 *
 * @method CommunicationPromotionProviderProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunicationPromotionProviderProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunicationPromotionProviderProduct[]    findAll()
 * @method CommunicationPromotionProviderProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunicationPromotionProviderProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationPromotionProviderProduct::class);
    }

    public function findForPromotionAndProvider(CommunicationPromotions $promotion, string $provider): ?CommunicationPromotionProviderProduct
    {
        return $this->findOneBy([
            'promotion' => $promotion,
            'provider' => $provider,
        ]);
    }

    /**
     * @return list<CommunicationPromotionProviderProduct>
     */
    public function findAllForPromotion(CommunicationPromotions $promotion): array
    {
        return $this->findBy(['promotion' => $promotion]);
    }
}
