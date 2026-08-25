<?php

namespace App\Service\Pricing;

use App\Entity\Account;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationContractRepository;
use App\Repository\CommunicationPackageRepository;
use App\Repository\CommunicationProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\ContractGatingScopeResolver;
use App\Service\Provider\ProductPriceResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Único punto de resolución de catálogo/precio para CommunicationPackage
 * (V2) — análogo a PackageSalePriceResolver mismo rol, pero decide TAMBIÉN
 * visibilidad (no solo precio): un cliente con contrato solo ve lo que su
 * contrato cubre, nada más.
 *
 * Desde la Fase 2 del rediseño de contratos por categoría, el ALCANCE de
 * esa restricción "todo o nada" depende de
 * `ContractGatingScopeResolver::isCategoryScoped()` (sys_config, kill
 * switch — ver esa clase):
 *
 *  - Alcance TENANT (default, comportamiento histórico sin cambios —
 *    catalogForTenantScope()/offerForTenantScope()): un contrato propio (o
 *    "por defecto") vigente restringe TODO el catálogo del cliente,
 *    cualquier categoría, a lo cubierto por sus contratos.
 *  - Alcance CATEGORY (nuevo, detrás del flag —
 *    catalogForCategoryScope()/offerForCategoryScope()): la restricción se
 *    evalúa POR CATEGORÍA (service+subservice, ver ServiceCategoryKey). Una
 *    categoría con al menos un contrato (propio o por defecto) del tenant
 *    queda restringida a lo cubierto; una categoría SIN ningún contrato
 *    sigue cayendo a MAX+margen, como si el tenant no tuviera contrato en
 *    absoluto — exactamente igual que hoy cuando NO hay NINGÚN contrato.
 *
 * Precedencia dentro de cada alcance (misma para catalogFor() y offerFor(),
 * nunca diverge):
 *  1. Contratos propios vigentes del tenant (de la categoría, en alcance
 *     CATEGORY) — si hay al menos uno, lo visible es EXACTAMENTE esos
 *     paquetes, a su precio.
 *  2. Sin contrato propio: contratos "por defecto" (tenant IS NULL)
 *     vigentes (de la categoría, en alcance CATEGORY) — mismo efecto de
 *     restricción de visibilidad.
 *  3. Sin ningún contrato: paquetes activos (de la categoría no gateada, en
 *     alcance CATEGORY), precio = MAX(price) entre los CommunicationProduct
 *     de cualquier proveedor que cubran la tupla (convertido a la moneda
 *     del cliente) + margen porcentual fijo global (sys_config). Un
 *     paquete sin ningún proveedor que lo cubra resuelve a UNAVAILABLE —
 *     catalogFor() lo excluye del resultado (vista de cliente); offerFor()
 *     lo devuelve tal cual (vista de admin, ver
 *     DashboardCommunicationPackagesController en Fase 3).
 */
class PackageCatalogResolver
{
    public const MARGIN_CONFIG_KEY = 'communications.catalog.max_price_margin_percent';

    public function __construct(
        private readonly CommunicationContractRepository $contractRepository,
        private readonly CommunicationPackageRepository $packageRepository,
        private readonly CommunicationProductRepository $productRepository,
        private readonly ProductPriceResolver $productPriceResolver,
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly ContractGatingScopeResolver $gatingScopeResolver,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    /**
     * @return list<ResolvedPackageOffer> nunca incluye UNAVAILABLE — un
     *   paquete "visible por contrato pero sin proveedor que lo cubra" no
     *   debe mostrarse para luego fallar al comprar.
     */
    public function catalogFor(Account $account, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        if (!$this->gatingScopeResolver->isCategoryScoped($account)) {
            return $this->catalogForTenantScope($account, $now);
        }

        return $this->catalogForCategoryScope($account, $now);
    }

    /**
     * Un solo paquete, mismo algoritmo de precedencia que catalogFor(). A
     * diferencia de éste, SÍ puede devolver UNAVAILABLE (vista de admin) o
     * null (no visible por contrato — ni siquiera aplica hablar de precio).
     */
    public function offerFor(CommunicationPackage $package, Account $account, ?\DateTimeImmutable $now = null): ?ResolvedPackageOffer
    {
        $now ??= new \DateTimeImmutable();

        if (!$this->gatingScopeResolver->isCategoryScoped($account)) {
            return $this->offerForTenantScope($package, $account, $now);
        }

        return $this->offerForCategoryScope($package, $account, $now);
    }

    /**
     * Guardia de venta: además de resolver, rechaza lo que no es vendible.
     * `PACKAGE_NOT_VISIBLE_FOR_CLIENT` = el cliente tiene contrato(s) pero
     * ninguno cubre este paquete (offerFor() devolvió null).
     * `PACKAGE_PRICE_UNAVAILABLE` = visible pero sin proveedor que lo cubra.
     *
     * Delega en offerFor() para TODO — incluido el alcance tenant/category
     * del kill switch: no hay ninguna ruta de compra que lo evite.
     */
    public function offerForSale(CommunicationPackage $package, Account $account, ?\DateTimeImmutable $now = null): ResolvedPackageOffer
    {
        $offer = $this->offerFor($package, $account, $now);
        if ($offer === null) {
            throw new MyCurrentException(
                'PACKAGE_NOT_VISIBLE_FOR_CLIENT',
                'Este paquete no está disponible para este cliente',
                Response::HTTP_CONFLICT,
            );
        }

        if ($offer->source === PackageOfferSourceEnum::UNAVAILABLE) {
            throw new MyCurrentException(
                'PACKAGE_PRICE_UNAVAILABLE',
                'No hay precio vigente para este paquete',
                Response::HTTP_CONFLICT,
            );
        }

        return $offer;
    }

    /**
     * Alcance TENANT (default) — comportamiento histórico, SIN CAMBIOS
     * respecto a antes de la Fase 2: la presencia de cualquier contrato
     * propio (o por defecto) restringe TODO el catálogo, cualquier
     * categoría.
     */
    private function catalogForTenantScope(Account $account, \DateTimeImmutable $now): array
    {
        $tenantContracts = $this->contractRepository->findActiveForTenant($account, $now);
        if ($tenantContracts !== []) {
            return $this->offersFromContracts($tenantContracts, PackageOfferSourceEnum::TENANT_CONTRACT);
        }

        $defaultContracts = $this->contractRepository->findActiveDefaults($now);
        if ($defaultContracts !== []) {
            return $this->offersFromContracts($defaultContracts, PackageOfferSourceEnum::DEFAULT_CONTRACT);
        }

        $offers = [];
        foreach ($this->packageRepository->findActiveCatalog($now) as $package) {
            $offer = $this->offerFromProductMax($package, $account);
            if ($offer->source !== PackageOfferSourceEnum::UNAVAILABLE) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * Alcance CATEGORY (Fase 2, detrás del flag) — la MISMA precedencia de
     * 3 niveles que catalogForTenantScope(), pero evaluada
     * independientemente por cada categoría (service+subservice) en vez de
     * una sola vez para todo el tenant.
     */
    private function catalogForCategoryScope(Account $account, \DateTimeImmutable $now): array
    {
        $tenantByCategory = $this->groupByCategory($this->contractRepository->findActiveForTenant($account, $now));
        $defaultByCategory = $this->groupByCategory($this->contractRepository->findActiveDefaults($now));

        $offers = [];
        $seenPackageIds = [];
        $gatedCategories = [];

        foreach ($tenantByCategory as $categoryKey => $contracts) {
            $gatedCategories[$categoryKey] = true;
            array_push($offers, ...$this->offersFromContracts($contracts, PackageOfferSourceEnum::TENANT_CONTRACT, $seenPackageIds));
        }
        foreach ($defaultByCategory as $categoryKey => $contracts) {
            if (isset($gatedCategories[$categoryKey])) {
                continue; // el tenant ya gobierna esta categoría con contrato propio
            }
            $gatedCategories[$categoryKey] = true;
            array_push($offers, ...$this->offersFromContracts($contracts, PackageOfferSourceEnum::DEFAULT_CONTRACT, $seenPackageIds));
        }

        // Categorías SIN ningún contrato (ni propio ni por defecto): caen a
        // MAX+margen, exactamente como el catálogo entero cae hoy cuando no
        // hay ningún contrato en absoluto (catalogForTenantScope() rama 3).
        foreach ($this->packageRepository->findActiveCatalogExcludingCategories(array_keys($gatedCategories), $now) as $package) {
            $offer = $this->offerFromProductMax($package, $account);
            if ($offer->source !== PackageOfferSourceEnum::UNAVAILABLE) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    private function offerForTenantScope(CommunicationPackage $package, Account $account, \DateTimeImmutable $now): ?ResolvedPackageOffer
    {
        $tenantContracts = $this->contractRepository->findActiveForTenant($account, $now);
        if ($tenantContracts !== []) {
            return $this->offerFromContractsForPackage($tenantContracts, $package, PackageOfferSourceEnum::TENANT_CONTRACT);
        }

        $defaultContracts = $this->contractRepository->findActiveDefaults($now);
        if ($defaultContracts !== []) {
            return $this->offerFromContractsForPackage($defaultContracts, $package, PackageOfferSourceEnum::DEFAULT_CONTRACT);
        }

        return $this->offerFromProductMax($package, $account);
    }

    private function offerForCategoryScope(CommunicationPackage $package, Account $account, \DateTimeImmutable $now): ?ResolvedPackageOffer
    {
        $categoryKey = $package->getServiceKey();

        $tenantContracts = $this->filterByCategory($this->contractRepository->findActiveForTenant($account, $now), $categoryKey);
        if ($tenantContracts !== []) {
            return $this->offerFromContractsForPackage($tenantContracts, $package, PackageOfferSourceEnum::TENANT_CONTRACT);
        }

        $defaultContracts = $this->filterByCategory($this->contractRepository->findActiveDefaults($now), $categoryKey);
        if ($defaultContracts !== []) {
            return $this->offerFromContractsForPackage($defaultContracts, $package, PackageOfferSourceEnum::DEFAULT_CONTRACT);
        }

        return $this->offerFromProductMax($package, $account);
    }

    /**
     * @param list<CommunicationContract> $contracts
     * @return array<string, list<CommunicationContract>>
     */
    private function groupByCategory(array $contracts): array
    {
        $grouped = [];
        foreach ($contracts as $contract) {
            $grouped[$contract->getServiceKey()][] = $contract;
        }

        return $grouped;
    }

    /**
     * @param list<CommunicationContract> $contracts
     * @return list<CommunicationContract>
     */
    private function filterByCategory(array $contracts, string $categoryKey): array
    {
        return array_values(array_filter(
            $contracts,
            static fn (CommunicationContract $c) => $c->getServiceKey() === $categoryKey,
        ));
    }

    /**
     * Aplana: por cada contrato, por cada CommunicationPackage de su
     * colección (Fase 6 — un contrato puede cubrir varios), una oferta.
     * Dedup defensivo por packageId — un paquete nunca debería aparecer dos
     * veces (upsertContract() lo routea siempre al mismo contrato), pero se
     * guarda como cinturón de seguridad ante contratos superpuestos creados
     * a mano.
     *
     * $seenPackageIds se pasa por referencia y, si el caller no da uno
     * explícito, cada llamada arranca con un array vacío propio (mismo
     * comportamiento que antes de la Fase 2, usado por
     * catalogForTenantScope() sin cambios). catalogForCategoryScope() SÍ
     * pasa uno compartido entre varias llamadas (una por categoría) para
     * que el dedup cubra TODO el catálogo, no solo un grupo — si el
     * invariante "todos los paquetes de un contrato comparten categoría"
     * se violara por un bug futuro, un paquete en contratos de más de una
     * categoría no aparecería duplicado.
     *
     * @param list<CommunicationContract> $contracts
     * @param array<int|string, bool> $seenPackageIds
     * @return list<ResolvedPackageOffer>
     */
    private function offersFromContracts(array $contracts, PackageOfferSourceEnum $source, array &$seenPackageIds = []): array
    {
        $offers = [];
        foreach ($contracts as $contract) {
            foreach ($contract->getPackages() as $package) {
                if (isset($seenPackageIds[$package->getId()])) {
                    continue;
                }
                $seenPackageIds[$package->getId()] = true;
                $offers[] = $this->offerFromContract($contract, $package, $source);
            }
        }

        return $offers;
    }

    /**
     * @param list<CommunicationContract> $contracts
     */
    private function offerFromContractsForPackage(array $contracts, CommunicationPackage $package, PackageOfferSourceEnum $source): ?ResolvedPackageOffer
    {
        foreach ($contracts as $contract) {
            if ($contract->getPackages()->contains($package)) {
                return $this->offerFromContract($contract, $package, $source);
            }
        }

        return null;
    }

    private function offerFromContract(CommunicationContract $contract, CommunicationPackage $package, PackageOfferSourceEnum $source): ResolvedPackageOffer
    {
        $offer = new ResolvedPackageOffer(
            package: $package,
            price: $contract->getPrice() ?? 0.0,
            currency: $contract->getCurrency() ?? 'USD',
            source: $source,
            contractId: $contract->getId(),
        );
        $package->setResolvedOffer($offer);

        return $offer;
    }

    private function offerFromProductMax(CommunicationPackage $package, Account $account): ResolvedPackageOffer
    {
        $candidates = $this->productRepository->findMatchingDestination(
            $package->getDestinationAmount(),
            $package->getDestinationCurrency(),
            $account->getEnvironment(),
        );

        $clientCurrency = $account->getClient()?->getCurrency();
        $best = null;
        foreach ($candidates as $product) {
            $resolved = $this->productPriceResolver->resolve($product, $clientCurrency, $account->getId());
            if ($best === null || $resolved->amount > $best->amount) {
                $best = $resolved;
            }
        }

        if ($best === null) {
            $this->providerLogger->warning('PackageCatalogResolver: paquete sin ningún proveedor que cubra su tupla de destino.', [
                'packageId' => $package->getId(),
                'destinationAmount' => $package->getDestinationAmount(),
                'destinationCurrency' => $package->getDestinationCurrency(),
                'accountId' => $account->getId(),
            ]);

            $offer = new ResolvedPackageOffer(
                package: $package,
                price: 0.0,
                currency: $clientCurrency ?? $package->getDestinationCurrency() ?? 'USD',
                source: PackageOfferSourceEnum::UNAVAILABLE,
                note: 'Ningún proveedor tiene un producto habilitado para esta tupla de destino.',
            );
            $package->setResolvedOffer($offer);

            return $offer;
        }

        $margin = $this->marginPercent();
        $priceWithMargin = round($best->amount * (1 + $margin / 100), 2);

        $offer = new ResolvedPackageOffer(
            package: $package,
            price: $priceWithMargin,
            currency: $best->currency ?? $clientCurrency ?? 'USD',
            source: PackageOfferSourceEnum::PRODUCT_MAX,
            note: $best->pendingNote,
        );
        $package->setResolvedOffer($offer);

        return $offer;
    }

    private function marginPercent(): float
    {
        $raw = $this->sysConfigRepo->findCachedValue(self::MARGIN_CONFIG_KEY);
        if ($raw === null || !is_numeric($raw)) {
            return 0.0;
        }

        return (float) $raw;
    }
}
