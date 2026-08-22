<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPromotions;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CommunicationPromotionProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly Security $security,
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
                        // compartido). `products` es una relación ManyToMany tipada a
                        // CommunicationClientPackage que API Platform serializa como
                        // to-many relation — NO admite elementos que no sean objetos de
                        // esa clase (AbstractItemNormalizer::normalizeCollectionOfRelations
                        // lanza "Unexpected non-object element in to-many relation" si se
                        // le mete un array plano, como se intentó aquí y tumbó prod el
                        // 2026-08-22). Se deja vacío a propósito; el listado de paquetes
                        // V2 debe exponerse en un campo propio, respaldado por una
                        // relación Doctrine real, no reutilizando este.
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
