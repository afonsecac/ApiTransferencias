<?php

namespace App\Service\Pricing;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Provider\Contract\ProviderPromotionCatalogInterface;
use App\Provider\Contract\PromotionCatalogQuery;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationPackageRepository;
use App\Repository\CommunicationProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orquestador Fase 5D: puebla automáticamente las equivalencias
 * tramo→producto por proveedor (CommunicationPackageProviderProduct) de una
 * promoción V2, usando el método común de la Fase 5C
 * (ProviderPromotionCatalogInterface::fetchPromotionProducts()).
 *
 * Por cada proveedor registrado que implemente esa interfaz:
 *  1. Pide sus productos candidatos para la ventana/tramos de la promoción.
 *  2. Cruza cada candidato contra NUESTRO catálogo ya sincronizado
 *     (CommunicationProductRepository, por provider+externalRef) — lo que
 *     devuelve fetchPromotionProducts() es la respuesta EN VIVO del
 *     proveedor, no necesariamente ya sincronizada como CommunicationProduct.
 *  3. Matchea por tupla contra cada tramo (monto flexible = null aplica a
 *     todos, como ETECSA) y hace upsert del vínculo.
 *
 * Nunca borra un vínculo existente que ya no aparezca en el resultado —
 * podría ser un vínculo manual del admin que el auto-poblado no reproduce
 * (ej. un producto-bono elegido a propósito). Solo agrega/actualiza.
 *
 * Se ejecuta automáticamente al crear la promoción (createV2()) y queda
 * disponible como acción manual "refrescar equivalencias" — cubre el caso
 * de que un proveedor publique su campaña después de creada la promoción.
 */
class CommunicationPromotionEquivalenceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProviderRegistry $providerRegistry,
        private readonly ProviderContextFactory $contextFactory,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
        private readonly CommunicationProductRepository $productRepository,
        private readonly CommunicationPackageRepository $packageRepository,
    ) {
    }

    /**
     * @param list<CommunicationPackage> $packages
     */
    public function populateEquivalences(CommunicationPromotions $promotion, array $packages): PromotionEquivalenceResult
    {
        if ($packages === []) {
            return new PromotionEquivalenceResult([], []);
        }

        $environment = $promotion->getEnvironment();
        $environmentType = $environment?->getType() ?? 'PROD';
        $currency = (string) $packages[0]->getDestinationCurrency();
        $amounts = array_map(static fn (CommunicationPackage $p) => (float) $p->getDestinationAmount(), $packages);

        $query = new PromotionCatalogQuery(
            destinationCurrency: $currency,
            destinationAmounts: $amounts,
            activeFrom: $promotion->getStartAt() ?? new \DateTimeImmutable(),
            activeTo: $promotion->getEndAt() ?? new \DateTimeImmutable(),
        );

        $providerReports = [];
        /** @var array<string, true> $coveredKeys "{packageId}:{provider}" ya cubiertos por este run o antes */
        $coveredKeys = $this->indexExistingBindings($packages);
        $capableProviders = [];

        foreach ($this->providerRegistry->allImplementing(ProviderPromotionCatalogInterface::class) as $provider) {
            $capableProviders[] = $provider->getCode()->value;
            $matched = 0;
            $error = null;

            try {
                $context = $this->contextFactory->forEnvironmentType($provider->getCode(), $environmentType, $environment?->getId());

                foreach ($provider->fetchPromotionProducts($context, $query) as $candidate) {
                    $product = $this->productRepository->findOneBy([
                        'provider' => $provider->getCode()->value,
                        'externalRef' => $candidate->externalId,
                    ]);
                    if ($product === null) {
                        // Candidato reportado por el proveedor pero todavía
                        // no sincronizado en nuestro catálogo — se salta, no
                        // se puede vincular a un CommunicationProduct que no
                        // existe. Queda como hueco (candidateAmount no cubre
                        // el tramo en $coveredKeys).
                        continue;
                    }

                    foreach ($packages as $package) {
                        if (!$this->coversTramo($candidate->destinationAmount, $candidate->destinationUnit, $package)) {
                            continue;
                        }

                        $key = $package->getId() . ':' . $provider->getCode()->value;
                        $this->upsertBinding($package, $provider->getCode()->value, $product);
                        if (!isset($coveredKeys[$key])) {
                            $coveredKeys[$key] = true;
                            $matched++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            $providerReports[] = ['provider' => $provider->getCode()->value, 'matched' => $matched, 'error' => $error];
        }

        $this->em->flush();

        return new PromotionEquivalenceResult($providerReports, $this->computeGaps($packages, $capableProviders, $coveredKeys));
    }

    /**
     * Acción manual "refrescar equivalencias" sobre una promoción V2 ya
     * creada — recarga sus tramos desde BD (no depende de tener la lista
     * en memoria) y vuelve a correr populateEquivalences(). Cubre el caso
     * de que un proveedor publique su campaña después de creada la
     * promoción (ver docs/promotion-provider-routing-por-tramo.md §6).
     */
    public function refreshForPromotion(CommunicationPromotions $promotion): PromotionEquivalenceResult
    {
        return $this->populateEquivalences($promotion, $this->packageRepository->findByPromotion($promotion));
    }

    /**
     * Vista de solo lectura de la cobertura actual — no llama a ningún
     * proveedor ni escribe nada, solo lee los vínculos ya persistidos.
     */
    public function coverage(CommunicationPromotions $promotion): PromotionEquivalenceResult
    {
        $packages = $this->packageRepository->findByPromotion($promotion);
        if ($packages === []) {
            return new PromotionEquivalenceResult([], []);
        }

        $capableProviders = array_map(
            static fn ($p) => $p->getCode()->value,
            $this->providerRegistry->allImplementing(ProviderPromotionCatalogInterface::class),
        );

        return new PromotionEquivalenceResult([], $this->computeGaps($packages, $capableProviders, $this->indexExistingBindings($packages)));
    }

    /**
     * @param list<CommunicationPackage> $packages
     * @return array<string, true>
     */
    private function indexExistingBindings(array $packages): array
    {
        $covered = [];
        foreach ($packages as $package) {
            foreach ($this->bindingRepo->findAllForPackage($package) as $binding) {
                $covered[$package->getId() . ':' . $binding->getProvider()] = true;
            }
        }

        return $covered;
    }

    /**
     * @param list<CommunicationPackage> $packages
     * @param list<string> $capableProviders
     * @param array<string, true> $coveredKeys
     * @return list<array{packageId: int, destinationAmount: float, missingProviders: list<string>}>
     */
    private function computeGaps(array $packages, array $capableProviders, array $coveredKeys): array
    {
        $gaps = [];
        foreach ($packages as $package) {
            $missing = [];
            foreach ($capableProviders as $provider) {
                if (!isset($coveredKeys[$package->getId() . ':' . $provider])) {
                    $missing[] = $provider;
                }
            }
            if ($missing !== []) {
                $gaps[] = [
                    'packageId' => (int) $package->getId(),
                    'destinationAmount' => (float) $package->getDestinationAmount(),
                    'missingProviders' => $missing,
                ];
            }
        }

        return $gaps;
    }

    private function coversTramo(?float $candidateAmount, ?string $candidateUnit, CommunicationPackage $package): bool
    {
        if ($candidateAmount === null) {
            // Monto flexible (ej. ETECSA) — cubre cualquier tramo.
            return true;
        }

        return DestinationKey::matches(
            $candidateAmount,
            $candidateUnit ?? '',
            (float) $package->getDestinationAmount(),
            (string) $package->getDestinationCurrency(),
        );
    }

    private function upsertBinding(CommunicationPackage $package, string $provider, CommunicationProduct $product): void
    {
        $existing = $this->bindingRepo->findForPackageAndProvider($package, $provider);
        if ($existing !== null) {
            $existing->setProduct($product);

            return;
        }

        $binding = (new CommunicationPackageProviderProduct())
            ->setCommunicationPackage($package)
            ->setProvider($provider)
            ->setProduct($product);
        $this->em->persist($binding);
    }
}
