<?php

namespace App\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProductSaleTypeMatcher;
use App\Service\Provider\ProviderAvailabilityService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige QUÉ proveedor despacha de verdad la venta de un CommunicationPackage
 * (V2) — reemplaza al rol de CommunicationSaleService::resolveAndGuardProvider(),
 * que decidía por el proveedor "dueño" del producto. Aquí es al revés: se
 * recorre la lista de PRIORIDAD del cliente (ClientProviderRouting::$priority)
 * y se busca, para cada proveedor en orden, un producto que cubra la tupla
 * de destino del paquete — el primero que sirve, gana.
 *
 * Mismo kill switch y fallback a proveedor único que
 * ProviderResolver::allowedForClient() usa hoy (comunications.provider.routing.enabled
 * + communications.provider.default) — sin filas de routing activas para el
 * cliente, o con el switch apagado, se prueba solo el proveedor por
 * defecto.
 *
 * Excepción al fallback: un paquete generado por una promoción
 * (CommunicationPackage::$promotion !== null, ver Fase 5) exige SIEMPRE
 * vínculo explícito — nunca cae a findMatchingDestination(). Es la garantía
 * de "nunca despachar en silencio con el producto equivocado": si el admin
 * (o el poblado automático por proveedor) no dejó una equivalencia para
 * este tramo, ese proveedor simplemente no participa, se prueba el
 * siguiente candidato de prioridad.
 */
class ProviderDispatchResolver
{
    public function __construct(
        private readonly ClientProviderRoutingRepository $routingRepo,
        private readonly CommunicationProductRepository $productRepository,
        private readonly CommunicationPackageProviderProductRepository $packageBindingRepo,
        private readonly ProviderAvailabilityService $availabilityService,
        private readonly ProductSaleTypeMatcher $saleTypeMatcher,
        private readonly SysConfigRepository $sysConfigRepo,
    ) {
    }

    public function select(Account $account, CommunicationPackage $package, ?string $saleType = null): SelectedDispatch
    {
        $environmentType = $account->getEnvironment()?->getType();

        foreach ($this->candidateProviders($account) as $provider) {
            if (!$this->availabilityService->canDispatchTo($provider->value, $environmentType)) {
                continue;
            }

            $product = $this->findDispatchableProduct($account, $package, $provider->value, $saleType);
            if ($product !== null) {
                return new SelectedDispatch($provider, $product, $product->getExternalRef());
            }
        }

        throw new MyCurrentException(
            'PACKAGE_NOT_DISPATCHABLE',
            'Ningún proveedor disponible puede despachar este paquete',
            Response::HTTP_CONFLICT,
        );
    }

    /**
     * @return list<CommunicationProviderEnum>
     */
    private function candidateProviders(Account $account): array
    {
        $client = $account->getClient();
        if ($client === null || !$this->routingEnabled()) {
            return [$this->defaultProvider()];
        }

        $rows = $this->routingRepo->findActiveProvidersOrderedForClient($client->getId());
        if ($rows === []) {
            return [$this->defaultProvider()];
        }

        $providers = [];
        foreach ($rows as $row) {
            $provider = CommunicationProviderEnum::tryFrom($row->getProvider() ?? '');
            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        return $providers === [] ? [$this->defaultProvider()] : $providers;
    }

    /**
     * El vínculo explícito (CommunicationPackageProviderProduct) gana
     * siempre que exista y siga siendo válido (habilitado + del entorno de
     * la cuenta) — el admin lo fijó a propósito, así que no se re-valida
     * contra saleType ni tupla.
     *
     * Sin vínculo (o si el vinculado ya no sirve): un paquete de catálogo
     * regular cae al matching automático de siempre — ningún paquete deja
     * de despachar solo porque todavía no fue vinculado a mano. Un paquete
     * de PROMOCIÓN (getPromotion() !== null) NUNCA cae al automático — sin
     * equivalencia explícita para este proveedor, no hay nada que devolver.
     */
    private function findDispatchableProduct(Account $account, CommunicationPackage $package, string $provider, ?string $saleType): ?CommunicationProduct
    {
        $bound = $this->packageBindingRepo->findForPackageAndProvider($package, $provider)?->getProduct();
        if ($bound !== null && $bound->isEnabled() && ($bound->getEnvironment() === null || $bound->getEnvironment() === $account->getEnvironment())) {
            return $bound;
        }

        if ($package->getPromotion() !== null) {
            return null;
        }

        $candidates = $this->productRepository->findMatchingDestination(
            $package->getDestinationAmount(),
            $package->getDestinationCurrency(),
            $account->getEnvironment(),
            $provider,
        );

        foreach ($candidates as $product) {
            if ($this->saleTypeMatcher->matches($product, $saleType)) {
                return $product;
            }
        }

        return null;
    }

    private function routingEnabled(): bool
    {
        return $this->sysConfigRepo->findCachedValue(ProviderResolver::ROUTING_ENABLED_KEY) !== '0';
    }

    private function defaultProvider(): CommunicationProviderEnum
    {
        $raw = $this->sysConfigRepo->findCachedValue(ProviderResolver::DEFAULT_PROVIDER_KEY);
        $provider = $raw !== null ? CommunicationProviderEnum::tryFrom($raw) : null;

        return $provider ?? CommunicationProviderEnum::ETECSA;
    }
}
