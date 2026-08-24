<?php

namespace App\Service\Provider;

use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Refresco periódico (pensado para cron externo, cada 6h desde medianoche
 * hora de Cuba — ver app:provider:refresh-catalogs) de TODOS los pares
 * (entorno, proveedor) que ya tienen productos sincronizados: vuelve a
 * sincronizar el catálogo (CommunicationCatalogSyncService, ya idempotente
 * a nivel de fila) y propaga cualquier cambio real de costo/moneda a los
 * CommunicationPricePackage ya creados que dependan de un producto que
 * cambió — nunca toca los que no cambiaron, y nunca toca una fila que no
 * sea autoManaged=true (creada por ClientCatalogImportService), para no
 * pisar un precio que un admin fijó a mano desde el dashboard.
 *
 * No crea combinaciones (producto × cuenta) nuevas — eso lo sigue haciendo
 * ClientCatalogImportService al cambiar el routing de un cliente. Este
 * servicio solo actualiza lo que ya existe.
 *
 * Fase 3 de la deprecación de V1 — verificado que V2 NO necesita su propio
 * pipeline aparte: PackageCatalogResolver::offerFromProductMax() resuelve el
 * precio MAX+margen leyendo CommunicationProduct EN VIVO en cada consulta
 * (mismo ProductPriceResolver que usa este cron), sin ninguna tabla
 * intermedia cacheada. El único insumo real que V2 necesita es
 * CommunicationProduct fresco, y eso ya lo garantiza
 * catalogSyncService->syncProducts() más abajo — que corre siempre, sin
 * mirar el switch V1/V2. Lo que SÍ es 100% V1 en este servicio es
 * propagateToPricePackages() (solo toca CommunicationPricePackage.autoManaged):
 * se puede borrar sin reemplazo el día que se borre V1 (Fase 5), no antes.
 *
 * Cuidado de implementación: CommunicationPricePackage tiene un callback
 * #[ORM\PreFlush] que toca updatedAt en CUALQUIER instancia manejada al
 * hacer flush(), sin importar si esa instancia concreta cambió. Por eso
 * todas las comparaciones "¿cambió algo?" se hacen con consultas
 * escalares/array (sin hidratar entidades) — solo se hidratan (con find())
 * las filas que YA se decidió actualizar, nunca las que solo se inspeccionan.
 */
class ProviderCatalogRefreshService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationCatalogSyncService $catalogSyncService,
        private readonly ProductPriceResolver $priceResolver,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    public function refreshAll(): ProviderCatalogRefreshResult
    {
        $pairs = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(p.environment) AS environmentId, p.provider AS provider')
            ->from(CommunicationProduct::class, 'p')
            ->getQuery()
            ->getScalarResult();

        $pairsProcessed = 0;
        $pairsFailed = 0;
        $productsChangedTotal = 0;
        $pricePackagesUpdatedTotal = 0;

        foreach ($pairs as $pair) {
            $environmentId = (int) $pair['environmentId'];
            $providerCode = CommunicationProviderEnum::tryFrom((string) $pair['provider']);
            if ($providerCode === null) {
                continue;
            }

            $environment = $this->em->getRepository(Environment::class)->find($environmentId);
            if ($environment === null) {
                continue;
            }

            try {
                $changedProductIds = $this->refreshPair($providerCode, $environment);
            } catch (\Throwable $e) {
                ++$pairsFailed;
                $this->providerLogger->error('Refresco de catálogo: falló para este par, se omite.', [
                    'provider' => $providerCode->value,
                    'environmentId' => $environmentId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            ++$pairsProcessed;
            $productsChangedTotal += count($changedProductIds);
            $pricePackagesUpdatedTotal += $this->propagateToPricePackages($changedProductIds);
        }

        $this->providerLogger->info('Refresco de catálogo completado.', [
            'pairsProcessed' => $pairsProcessed,
            'pairsFailed' => $pairsFailed,
            'productsChanged' => $productsChangedTotal,
            'pricePackagesUpdated' => $pricePackagesUpdatedTotal,
        ]);

        return new ProviderCatalogRefreshResult($pairsProcessed, $pairsFailed, $productsChangedTotal, $pricePackagesUpdatedTotal);
    }

    /**
     * @return list<int> IDs de producto cuyo price/priceCurrency/destinationAmount/destinationUnit cambió de verdad
     */
    private function refreshPair(CommunicationProviderEnum $providerCode, Environment $environment): array
    {
        $before = $this->snapshotRelevantFields($environment->getId(), $providerCode->value);

        $this->catalogSyncService->syncProducts($providerCode, $environment);
        // Vacía el UnitOfWork por completo: la comparación "después" debe
        // leer de la BD, no reusar instancias ya hidratadas por el sync
        // (EntityManager::clear() de Doctrine ORM 3 no admite filtrar por
        // clase). $environment->getId() sigue siendo seguro tras esto (es
        // un escalar ya cargado, no dispara lazy-loading).
        $this->em->clear();

        $after = $this->snapshotRelevantFields($environment->getId(), $providerCode->value);

        $changed = [];
        foreach ($after as $productId => $newValues) {
            $oldValues = $before[$productId] ?? null;
            if ($oldValues === null) {
                // Producto nuevo: no hay PricePackage previo que actualizar.
                continue;
            }
            if ($oldValues !== $newValues) {
                $changed[] = $productId;
            }
        }

        return $changed;
    }

    /**
     * @return array<int, array{price: ?float, priceCurrency: ?string, destinationAmount: ?float, destinationUnit: ?string}>
     */
    private function snapshotRelevantFields(int $environmentId, string $provider): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('p.id AS id, p.price AS price, p.priceCurrency AS priceCurrency, p.destinationAmount AS destinationAmount, p.destinationUnit AS destinationUnit')
            ->from(CommunicationProduct::class, 'p')
            ->andWhere('p.environment = :environmentId')
            ->andWhere('p.provider = :provider')
            ->setParameter('environmentId', $environmentId)
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getScalarResult();

        $snapshot = [];
        foreach ($rows as $row) {
            $snapshot[(int) $row['id']] = [
                'price' => $row['price'] !== null ? (float) $row['price'] : null,
                'priceCurrency' => $row['priceCurrency'],
                'destinationAmount' => $row['destinationAmount'] !== null ? (float) $row['destinationAmount'] : null,
                'destinationUnit' => $row['destinationUnit'],
            ];
        }

        return $snapshot;
    }

    /**
     * @param list<int> $changedProductIds
     */
    private function propagateToPricePackages(array $changedProductIds): int
    {
        if ($changedProductIds === []) {
            return 0;
        }

        $idsToUpdate = $this->em->createQueryBuilder()
            ->select('pp.id')
            ->from(CommunicationPricePackage::class, 'pp')
            ->andWhere('pp.product IN (:productIds)')
            ->andWhere('pp.autoManaged = true')
            ->setParameter('productIds', $changedProductIds)
            ->getQuery()
            ->getSingleColumnResult();

        $updated = 0;
        foreach ($idsToUpdate as $id) {
            $pricePackage = $this->em->find(CommunicationPricePackage::class, $id);
            if ($pricePackage === null) {
                continue;
            }

            $product = $pricePackage->getProduct();
            $client = $pricePackage->getTenant()?->getClient();
            if ($product === null) {
                continue;
            }

            $pricePackage->setPrice($product->getPrice() ?? 0.0);
            if ($product->getPriceCurrency() !== null) {
                $pricePackage->setPriceCurrency($product->getPriceCurrency());
            }

            $resolved = $this->priceResolver->resolve($product, $client?->getCurrency(), $pricePackage->getTenant()?->getId());
            $pricePackage->setAmount($resolved->amount);
            if ($resolved->currency !== null) {
                $pricePackage->setCurrency($resolved->currency);
            }
            $pricePackage->setConversionRate($resolved->conversionRate);
            $pricePackage->setConversionRateDate($resolved->conversionRateDate);
            $pricePackage->setKnowMore($resolved->pendingNote);

            ++$updated;
        }

        if ($updated > 0) {
            $this->em->flush();
        }

        return $updated;
    }
}
