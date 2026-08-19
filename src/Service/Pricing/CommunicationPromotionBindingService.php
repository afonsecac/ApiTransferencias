<?php

namespace App\Service\Pricing;

use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotionProviderProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Provider\ProviderRegistry;
use App\Repository\CommunicationProductRepository;
use App\Repository\CommunicationPromotionProviderProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vínculo explícito promoción→producto por proveedor —
 * ver App\Entity\CommunicationPromotionProviderProduct. A diferencia de
 * CommunicationPackageBindingService, no hay matching automático por tupla:
 * una promoción no tiene un destinationAmount/destinationCurrency único, así
 * que "candidates" es siempre el catálogo completo habilitado del
 * proveedor (el admin elige a mano). La lista de proveedores nunca se
 * hardcodea aquí: sale de ProviderRegistry::registered().
 */
class CommunicationPromotionBindingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationPromotionProviderProductRepository $bindingRepo,
        private readonly CommunicationProductRepository $productRepository,
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    /**
     * Una fila por cada proveedor REGISTRADO en el sistema (no solo los que
     * ya tienen vínculo) — para que el admin vea explícitamente qué
     * proveedores le faltan por cubrir.
     *
     * @return list<array{provider: string, boundProduct: ?CommunicationProduct, candidates: list<CommunicationProduct>}>
     */
    public function listBindings(CommunicationPromotions $promotion, ?Environment $environment): array
    {
        $bindingsByProvider = [];
        foreach ($this->bindingRepo->findAllForPromotion($promotion) as $binding) {
            $bindingsByProvider[$binding->getProvider()] = $binding->getProduct();
        }

        $rows = [];
        foreach ($this->providerRegistry->registered() as $providerEnum) {
            $provider = $providerEnum->value;
            $rows[] = [
                'provider' => $provider,
                'boundProduct' => $bindingsByProvider[$provider] ?? null,
                'candidates' => $this->productRepository->findAllByProvider($provider, $environment),
            ];
        }

        return $rows;
    }

    /**
     * @throws MyCurrentException
     */
    public function setBinding(CommunicationPromotions $promotion, string $provider, int $productId): CommunicationPromotionProviderProduct
    {
        $this->assertProviderRegistered($provider);

        $product = $this->productRepository->find($productId);
        if ($product === null) {
            throw new MyCurrentException('COMMUNICATION_PRODUCT_NOT_FOUND', 'Communication product not found', 404);
        }

        $binding = $this->bindingRepo->findForPromotionAndProvider($promotion, $provider);
        if ($binding === null) {
            $binding = (new CommunicationPromotionProviderProduct())
                ->setPromotion($promotion)
                ->setProvider($provider);
            $this->em->persist($binding);
        }
        $binding->setProduct($product);

        $this->em->flush();

        return $binding;
    }

    /**
     * @throws MyCurrentException
     */
    public function removeBinding(CommunicationPromotions $promotion, string $provider): void
    {
        $this->assertProviderRegistered($provider);

        $binding = $this->bindingRepo->findForPromotionAndProvider($promotion, $provider);
        if ($binding === null) {
            return;
        }

        $this->em->remove($binding);
        $this->em->flush();
    }

    /**
     * @throws MyCurrentException
     */
    private function assertProviderRegistered(string $provider): void
    {
        $known = array_map(static fn ($p) => $p->value, $this->providerRegistry->registered());
        if (!in_array($provider, $known, true)) {
            throw new MyCurrentException('PROVIDER_NOT_REGISTERED', "El proveedor {$provider} no está registrado", 404);
        }
    }
}
