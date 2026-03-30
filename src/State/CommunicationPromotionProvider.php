<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPromotions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CommunicationPromotionProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $promotions = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($promotions === null) {
            return $promotions;
        }
        if (!$promotions instanceof \Countable || !$promotions instanceof \IteratorAggregate) {
            return $promotions;
        }
        if ($promotions->count() > 0) {
            $tenant = $this->security->getUser();
            if ($tenant instanceof Account) {
                $items = iterator_to_array($promotions->getIterator());
                foreach ($items as $promotion) {
                    if ($promotion instanceof CommunicationPromotions) {
                        $products = $promotion->getProducts()->filter(
                            function (\App\Entity\CommunicationClientPackage $clientPackage) {
                                $user = $this->security->getUser();

                                return $user instanceof Account && $clientPackage->getTenant()?->getId(
                                    ) === $user->getId();
                            }
                        );
                        $promotion->setProductsTemp($products);
                    }
                }
            }
        }

        return $promotions;
    }

}
