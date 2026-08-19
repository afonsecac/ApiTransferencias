<?php

namespace App\Service\Pricing;

use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPricePackageRepository;
use App\Service\Provider\ProductPriceResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Único punto de resolución del precio de venta de un
 * CommunicationClientPackage — compartido por el listado
 * (GET /communication/packages) y por la venta (CommunicationSaleService).
 * Antes de este rediseño existían DOS fuentes que podían divergir:
 * processRecharge()/processReserve() cobraban ClientPackage.amount y
 * executeSale() cobraba priceClientPackage.amount, y el refresco periódico
 * de catálogo solo actualizaba el segundo — tras un cambio de tarifa del
 * proveedor las recargas seguían cobrando el precio viejo indefinidamente.
 * Con un único resolver consultado en ambos sitios esa divergencia deja de
 * ser posible por construcción.
 *
 * Orden de resolución:
 *  1. Contrato CONGELADO (CommunicationClientPackage::$priceClientPackage)
 *     — típicamente de una promoción. Máxima prioridad: preserva tal cual
 *     los precios ya comprometidos con el cliente.
 *  2. Contrato vigente por (tenant, paquete referencia) — ver
 *     CommunicationClientPackage::resolveContractKey() y
 *     PackageContractService.
 *  3. Sin contrato: costo del producto convertido a la moneda del cliente
 *     (ProductPriceResolver, reutilizado tal cual — sin duplicar
 *     conversión/fallback).
 *  4. Ni contrato ni producto resoluble (fila legacy anterior al backfill
 *     de paquetes referencia): snapshot crudo persistido, con warning.
 */
class PackageSalePriceResolver
{
    public function __construct(
        private readonly CommunicationPricePackageRepository $contractRepository,
        private readonly ProductPriceResolver $productPriceResolver,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    public function resolve(CommunicationClientPackage $package, Account $account): ResolvedSalePrice
    {
        return $this->doResolve($package, $account, null);
    }

    /**
     * Guardia de venta: además de resolver, rechaza un precio NO RESOLUBLE
     * (LEGACY_SNAPSHOT — ni contrato ni producto detrás, típicamente una
     * fila anterior al backfill de paquetes referencia). Un CONTRACT o
     * PRODUCT en $0 es un precio real y deliberado (paquete gratuito/de
     * compensación, ya usado así antes de este rediseño — ver
     * ProviderFunctionalTestCase::createSellablePackage()) y se admite. El
     * listado nunca usa este método: ahí un precio "malo" se muestra con su
     * `note`, no se oculta ni se lanza.
     */
    public function resolveForSale(CommunicationClientPackage $package, Account $account): ResolvedSalePrice
    {
        $resolved = $this->resolve($package, $account);
        if ($resolved->source === PriceSourceEnum::LEGACY_SNAPSHOT && $resolved->amount <= 0) {
            throw new MyCurrentException(
                'PACKAGE_PRICE_UNAVAILABLE',
                'No hay precio vigente para este paquete',
                Response::HTTP_CONFLICT,
            );
        }

        return $resolved;
    }

    /**
     * Resuelve un lote de paquetes con como máximo 1 query de contratos, en
     * vez de una por paquete — el listado pagina a 100 y no puede volverse
     * N+1. Inyecta el resultado en cada paquete vía setResolvedSalePrice();
     * getAmount()/getCurrency() lo consultan automáticamente después.
     *
     * @param iterable<CommunicationClientPackage> $packages
     */
    public function resolveMany(iterable $packages, Account $account): void
    {
        $packages = is_array($packages) ? $packages : iterator_to_array($packages);

        $referenceIds = [];
        foreach ($packages as $package) {
            if ($package->getPriceClientPackage() !== null) {
                continue; // ya trae su propio contrato congelado, no entra en el lote
            }
            $key = $package->resolveContractKey();
            if ($key !== null) {
                $referenceIds[] = $key;
            }
        }

        $preloaded = $referenceIds !== []
            ? $this->contractRepository->findActiveContractsFor($account, array_values(array_unique($referenceIds)))
            : [];

        foreach ($packages as $package) {
            $package->setResolvedSalePrice($this->doResolve($package, $account, $preloaded));
        }
    }

    /**
     * @param array<int, CommunicationPricePackage>|null $preloadedContracts indexado por id de paquete referencia; null = consultar 1 a 1 (resolve() suelto)
     */
    private function doResolve(CommunicationClientPackage $package, Account $account, ?array $preloadedContracts): ResolvedSalePrice
    {
        $now = new \DateTimeImmutable();

        $frozen = $package->getPriceClientPackage();
        if ($frozen !== null && $this->isActiveContract($frozen, $now)) {
            return $this->fromContract($frozen);
        }

        $referenceId = $package->resolveContractKey();
        if ($referenceId !== null) {
            $contract = $preloadedContracts !== null
                ? ($preloadedContracts[$referenceId] ?? null)
                : $this->contractRepository->findActiveContract($account, $referenceId, $now);

            if ($contract !== null) {
                return $this->fromContract($contract);
            }
        }

        $product = $package->resolveProduct();
        if ($product !== null) {
            $resolved = $this->productPriceResolver->resolve($product, $account->getClient()?->getCurrency(), $account->getId());
            $currency = $resolved->currency ?? $package->getCurrency() ?? $product->getPriceCurrency();
            if ($currency !== null) {
                return new ResolvedSalePrice(
                    amount: $resolved->amount,
                    currency: $currency,
                    source: PriceSourceEnum::PRODUCT,
                    conversionRate: $resolved->conversionRate,
                    conversionRateDate: $resolved->conversionRateDate,
                    note: $resolved->pendingNote,
                );
            }
        }

        $this->providerLogger->warning('PackageSalePriceResolver: paquete sin contrato ni producto resoluble, se usa el snapshot legacy.', [
            'packageId' => $package->getId(),
            'accountId' => $account->getId(),
        ]);

        return new ResolvedSalePrice(
            amount: $package->getAmount() ?? 0.0,
            currency: $package->getCurrency() ?? 'USD',
            source: PriceSourceEnum::LEGACY_SNAPSHOT,
            note: 'Paquete sin contrato ni producto asociado — precio legacy sin garantías, revisar manualmente.',
        );
    }

    private function isActiveContract(CommunicationPricePackage $contract, \DateTimeImmutable $now): bool
    {
        if (!$contract->isContract() || $contract->isIsActive() !== true) {
            return false;
        }

        $activeStartAt = $contract->getActiveStartAt();
        if ($activeStartAt !== null && $activeStartAt > $now) {
            return false;
        }

        $activeEndAt = $contract->getActiveEndAt();

        return $activeEndAt === null || $activeEndAt > $now;
    }

    private function fromContract(CommunicationPricePackage $contract): ResolvedSalePrice
    {
        return new ResolvedSalePrice(
            amount: $contract->getAmount() ?? 0.0,
            currency: $contract->getCurrency() ?? 'USD',
            source: PriceSourceEnum::CONTRACT,
            contractId: $contract->getId(),
            conversionRate: $contract->getConversionRate(),
            conversionRateDate: $contract->getConversionRateDate(),
            note: $contract->getKnowMore(),
        );
    }
}
