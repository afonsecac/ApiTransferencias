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
 * recorre la lista de candidatos del cliente (ClientProviderRouting) y se
 * busca, para cada proveedor en orden, un producto que cubra la tupla de
 * destino del paquete — el primero que sirve, gana.
 *
 * La lista de candidatos se construye en tres pasos, ver candidateProviders():
 *   1. Se descartan las filas cuyo entorno/tipo de venta/servicio/subservicio
 *      no aplican a esta venta (null en la fila = comodín, aplica siempre).
 *   2. Las filas aplicables se ordenan por ESPECIFICIDAD (cuántas
 *      dimensiones fija, no comodín) de mayor a menor — a igual
 *      especificidad manda ClientProviderRouting::$priority/id, en el
 *      mismo orden que ya trae el repositorio (usort() es estable en
 *      PHP 8).
 *   3. Cada fila aporta su `provider` y, si tiene, su `fallbackProvider` a
 *      continuación — ver selectExcluding() para cómo se usa esto en un
 *      failover tras rechazo (tanto en la selección inicial como al
 *      reintentar).
 *
 * Mismo kill switch y fallback a proveedor único que
 * ProviderResolver::allowedForClient() usa hoy (comunications.provider.routing.enabled
 * + communications.provider.default) — sin filas de routing aplicables para
 * el cliente, o con el switch apagado, se prueba solo el proveedor por
 * defecto.
 *
 * Excepción al fallback: un paquete generado por una promoción
 * (CommunicationPackage::$promotion !== null, ver Fase 5) exige SIEMPRE
 * vínculo explícito — nunca cae a findMatchingDestination(). Es la garantía
 * de "nunca despachar en silencio con el producto equivocado": si el admin
 * (o el poblado automático por proveedor) no dejó una equivalencia para
 * este tramo, ese proveedor simplemente no participa, se prueba el
 * siguiente candidato.
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
        $selected = $this->selectExcluding($account, $package, $saleType, []);
        if ($selected !== null) {
            return $selected;
        }

        throw new MyCurrentException(
            'PACKAGE_NOT_DISPATCHABLE',
            'Ningún proveedor disponible puede despachar este paquete',
            Response::HTTP_CONFLICT,
        );
    }

    /**
     * Igual que select(), salvo que (a) salta cualquier proveedor en
     * $excludeProviders (ya intentado por SaleProviderFailoverService) y
     * (b) devuelve null en vez de lanzar cuando nadie puede despachar —
     * quien llama decide qué hacer con "no hay a quién más intentarle".
     *
     * @param list<CommunicationProviderEnum> $excludeProviders
     */
    public function selectExcluding(Account $account, CommunicationPackage $package, ?string $saleType, array $excludeProviders): ?SelectedDispatch
    {
        $environmentType = $account->getEnvironment()?->getType();

        foreach ($this->candidateProviders($account, $package, $saleType) as $provider) {
            if (in_array($provider, $excludeProviders, true)) {
                continue;
            }

            if (!$this->availabilityService->canDispatchTo($provider->value, $environmentType)) {
                continue;
            }

            $product = $this->findDispatchableProduct($account, $package, $provider->value, $saleType);
            if ($product !== null) {
                return new SelectedDispatch($provider, $product, $product->getExternalRef());
            }
        }

        return null;
    }

    /**
     * @return list<CommunicationProviderEnum>
     */
    private function candidateProviders(Account $account, CommunicationPackage $package, ?string $saleType): array
    {
        $client = $account->getClient();
        if ($client === null || !$this->routingEnabled()) {
            return [$this->defaultProvider()];
        }

        $rows = $this->routingRepo->findActiveRouteScopesForClient($client->getId());
        if ($rows === []) {
            return [$this->defaultProvider()];
        }

        $environmentId = $account->getEnvironment()?->getId();
        $service = $package->getService();
        $packageServiceName = $service['name'] ?? null;
        $packageSubserviceName = $service['subservice']['name'] ?? null;

        $applicable = array_values(array_filter(
            $rows,
            fn (array $row) => $this->rowApplies($row, $environmentId, $saleType, $packageServiceName, $packageSubserviceName),
        ));

        if ($applicable === []) {
            return [$this->defaultProvider()];
        }

        usort($applicable, fn (array $a, array $b) => $this->specificity($b) <=> $this->specificity($a));

        $providers = [];
        foreach ($applicable as $row) {
            foreach ([$row['provider'], $row['fallbackProvider']] as $code) {
                $provider = CommunicationProviderEnum::tryFrom($code ?? '');
                if ($provider !== null && !in_array($provider, $providers, true)) {
                    $providers[] = $provider;
                }
            }
        }

        return $providers === [] ? [$this->defaultProvider()] : $providers;
    }

    /**
     * @param array{environmentId:?int, saleType:?string, serviceName:?string, subserviceName:?string} $row
     */
    private function rowApplies(array $row, ?int $environmentId, ?string $saleType, ?string $packageServiceName, ?string $packageSubserviceName): bool
    {
        if ($row['environmentId'] !== null && $row['environmentId'] !== $environmentId) {
            return false;
        }
        if ($row['saleType'] !== null && $row['saleType'] !== $saleType) {
            return false;
        }
        if ($row['serviceName'] !== null && trim($row['serviceName']) !== trim($packageServiceName ?? '')) {
            return false;
        }
        if ($row['subserviceName'] !== null && trim($row['subserviceName']) !== trim($packageSubserviceName ?? '')) {
            return false;
        }

        return true;
    }

    /**
     * @param array{environmentId:?int, saleType:?string, serviceName:?string, subserviceName:?string} $row
     */
    private function specificity(array $row): int
    {
        return ($row['environmentId'] !== null ? 8 : 0)
            + ($row['saleType'] !== null ? 4 : 0)
            + ($row['serviceName'] !== null ? 2 : 0)
            + ($row['subserviceName'] !== null ? 1 : 0);
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
