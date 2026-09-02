<?php

namespace App\Service\Pricing;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Provider\ProviderRegistry;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationPackageRepository;
use App\Repository\CommunicationProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vínculo explícito paquete→producto por proveedor (V2) —
 * ver App\Entity\CommunicationPackageProviderProduct. La lista de
 * proveedores nunca se hardcodea aquí: sale de
 * ProviderRegistry::registered(), así que un proveedor nuevo (adaptador
 * tageado + caso de CommunicationProviderEnum) aparece solo en esta
 * pantalla sin tocar este servicio.
 */
class CommunicationPackageBindingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
        private readonly CommunicationProductRepository $productRepository,
        private readonly CommunicationPackageRepository $packageRepository,
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    /**
     * Una fila por cada proveedor REGISTRADO en el sistema (no solo los que
     * ya tienen candidatos) — para que el admin vea explícitamente qué
     * proveedores le faltan por cubrir. Si el matching automático por
     * tupla no encuentra NINGÚN candidato para un proveedor (ej. ETECSA:
     * sus productos no traen destinationAmount/destinationUnit poblados,
     * así que nunca matchean por tupla), se cae al catálogo COMPLETO
     * habilitado de ese proveedor — el admin necesita poder elegir a mano
     * cuando el automático no tiene nada que ofrecer.
     *
     * @return list<array{provider: string, boundProduct: ?CommunicationProduct, candidates: list<CommunicationProduct>, autoMatched: bool}>
     */
    public function listBindings(CommunicationPackage $package, ?Environment $environment): array
    {
        $bindingsByProvider = [];
        foreach ($this->bindingRepo->findAllForPackage($package) as $binding) {
            $bindingsByProvider[$binding->getProvider()] = $binding->getProduct();
        }

        $candidatesByProvider = [];
        foreach ($this->productRepository->findMatchingDestination(
            (float) $package->getDestinationAmount(),
            (string) $package->getDestinationCurrency(),
            $environment,
        ) as $product) {
            $candidatesByProvider[$product->getProvider()][] = $product;
        }

        $rows = [];
        foreach ($this->providerRegistry->registered() as $providerEnum) {
            $provider = $providerEnum->value;
            $autoMatched = isset($candidatesByProvider[$provider]);
            $candidates = $autoMatched
                ? $candidatesByProvider[$provider]
                : $this->productRepository->findAllByProvider($provider, $environment);
            $rows[] = [
                'provider' => $provider,
                'boundProduct' => $bindingsByProvider[$provider] ?? null,
                'candidates' => $candidates,
                'autoMatched' => $autoMatched,
            ];
        }

        return $rows;
    }

    /**
     * Cobertura por proveedor de todos los paquetes generados por una
     * promoción V2 — el tab "Proveedores" del flujo de promociones se apoya
     * en esto para mostrar de una vez qué paquetes quedan sin despachar por
     * qué proveedor (ver ProviderDispatchResolver::findDispatchableProduct(),
     * que nunca hace auto-match para un paquete de promoción). No incluye
     * candidatos — esta lista es de resumen; los candidatos por paquete se
     * piden bajo demanda con listBindings().
     *
     * @return list<array{package: CommunicationPackage, bindings: list<array{provider: string, product: CommunicationProduct}>, missingProviders: list<string>}>
     */
    public function listPromotionPackageBindings(CommunicationPromotions $promotion): array
    {
        $registeredProviders = array_map(static fn ($p) => $p->value, $this->providerRegistry->registered());

        $rows = [];
        foreach ($this->packageRepository->findByPromotion($promotion) as $package) {
            $bindings = [];
            $boundProviders = [];
            foreach ($this->bindingRepo->findAllForPackage($package) as $binding) {
                $bindings[] = ['provider' => $binding->getProvider(), 'product' => $binding->getProduct()];
                $boundProviders[] = $binding->getProvider();
            }

            $rows[] = [
                'package' => $package,
                'bindings' => $bindings,
                'missingProviders' => array_values(array_diff($registeredProviders, $boundProviders)),
            ];
        }

        return $rows;
    }

    /**
     * @throws MyCurrentException
     */
    public function setBinding(CommunicationPackage $package, string $provider, int $productId): CommunicationPackageProviderProduct
    {
        $this->assertProviderRegistered($provider);

        $product = $this->productRepository->find($productId);
        if ($product === null) {
            throw new MyCurrentException('COMMUNICATION_PRODUCT_NOT_FOUND', 'Communication product not found', 404);
        }

        $binding = $this->bindingRepo->findForPackageAndProvider($package, $provider);
        if ($binding === null) {
            $binding = (new CommunicationPackageProviderProduct())
                ->setCommunicationPackage($package)
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
    public function removeBinding(CommunicationPackage $package, string $provider): void
    {
        $this->assertProviderRegistered($provider);

        $binding = $this->bindingRepo->findForPackageAndProvider($package, $provider);
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
