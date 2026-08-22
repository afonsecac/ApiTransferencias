<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Repository\CommunicationPackageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CommunicationPromotionProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly Security $security,
        private readonly CommunicationPackageRepository $packageRepository,
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
                    if (!$promotion instanceof CommunicationPromotions) {
                        continue;
                    }

                    if ($promotion->isV2()) {
                        // V2: no genera CommunicationClientPackage por tenant (catálogo
                        // compartido), así que `products` se puebla desde el
                        // CommunicationPackage de la promoción vía su FK `promotion`.
                        $packages = $this->packageRepository->findByPromotion($promotion);
                        $products = array_map(
                            static fn (CommunicationPackage $package) => [
                                'id' => $package->getId(),
                                'description' => $package->getDescription(),
                                'name' => $package->getName(),
                            ],
                            $packages
                        );
                        $promotion->setProductsTemp(new ArrayCollection($products));
                        continue;
                    }

                    $products = $promotion->getProducts()->filter(
                        function (\App\Entity\CommunicationClientPackage $clientPackage) {
                            $user = $this->security->getUser();

                            return $user instanceof Account && $clientPackage->getTenant()?->getId(
                                ) === $user->getId();
                        }
                    );
                    $promotion->setProductsTemp(new ArrayCollection($products->getValues()));
                }
            }
        }

        return $promotions;
    }

}
