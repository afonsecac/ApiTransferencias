<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationPromotions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CommunicationPromotionProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $itemProvider,
        #[Autowire(service: 'doctrine.orm.entity_manager')]
        private readonly EntityManagerInterface $em,
        private readonly Security $security
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $promotions = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($promotions->count() > 0) {
            $tenant = $this->security->getUser();
            if (!is_null($tenant) && $tenant instanceof Account) {
                $countPromotion = $promotions->count();
                for ($i = 0; $i < $countPromotion; $i++) {
                    $promotion = $promotions->getIterator()->offsetGet($i);
                    if ($promotion instanceof CommunicationPromotions) {
                        $products = $promotion->getProducts()->filter(function (\App\Entity\CommunicationClientPackage $clientPackage) {
                            $user = $this->security->getUser();
                            return $user instanceof Account && $clientPackage->getTenant()?->getId() === $user->getId();
                        });
                        $promotion->setProductsTemp($products);
                        $promotions->getIterator()->offsetSet($i, $promotion);
                    }
                }
            }
        }

        return $promotions;
    }

}
