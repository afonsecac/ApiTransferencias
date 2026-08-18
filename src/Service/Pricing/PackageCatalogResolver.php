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
 * Precedencia (misma para catalogFor() y offerFor(), nunca diverge):
 *  1. Contratos propios vigentes del tenant — si hay al menos uno, el
 *     catálogo visible es EXACTAMENTE esos paquetes, a su precio.
 *  2. Sin contrato propio: contratos "por defecto" (tenant IS NULL)
 *     vigentes — mismo efecto de restricción de visibilidad.
 *  3. Sin ningún contrato: catálogo activo completo, precio = MAX(price)
 *     entre los CommunicationProduct de cualquier proveedor que cubran la
 *     tupla (convertido a la moneda del cliente) + margen porcentual fijo
 *     global (sys_config). Un paquete sin ningún proveedor que lo cubra
 *     resuelve a UNAVAILABLE — catalogFor() lo excluye del resultado (vista
 *     de cliente); offerFor() lo devuelve tal cual (vista de admin, ver
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
     * Un solo paquete, mismo algoritmo de precedencia que catalogFor(). A
     * diferencia de éste, SÍ puede devolver UNAVAILABLE (vista de admin) o
     * null (no visible por contrato — ni siquiera aplica hablar de precio).
     */
    public function offerFor(CommunicationPackage $package, Account $account, ?\DateTimeImmutable $now = null): ?ResolvedPackageOffer
    {
        $now ??= new \DateTimeImmutable();

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

    /**
     * Guardia de venta: además de resolver, rechaza lo que no es vendible.
     * `PACKAGE_NOT_VISIBLE_FOR_CLIENT` = el cliente tiene contrato(s) pero
     * ninguno cubre este paquete (offerFor() devolvió null).
     * `PACKAGE_PRICE_UNAVAILABLE` = visible pero sin proveedor que lo cubra
     * (mismo codeWork que ya usa PackageSalePriceResolver para V1).
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
     * Aplana: por cada contrato, por cada CommunicationPackage de su
     * colección (Fase 6 — un contrato puede cubrir varios), una oferta.
     * Dedup defensivo por packageId — un paquete nunca debería aparecer dos
     * veces (upsertContract() lo routea siempre al mismo contrato), pero se
     * guarda como cinturón de seguridad ante contratos superpuestos creados
     * a mano.
     *
     * @param list<CommunicationContract> $contracts
     * @return list<ResolvedPackageOffer>
     */
    private function offersFromContracts(array $contracts, PackageOfferSourceEnum $source): array
    {
        $offers = [];
        $seenPackageIds = [];
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
