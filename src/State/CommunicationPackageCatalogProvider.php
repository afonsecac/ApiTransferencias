<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Catalog\ClientServiceProviderCoverageResolver;
use App\Service\Catalog\MarketPackageEnricher;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\ResolvedPackageOffer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Catálogo agnóstico de proveedor (V2) para la app móvil — a diferencia de
 * CommunicationClientPackageProvider (V1), no delega en el provider Doctrine
 * estándar: PackageCatalogResolver::catalogFor() YA decide el conjunto
 * completo de paquetes visibles (no solo su precio) — de ahí que
 * CurrentUserExtension no necesite intervenir en esta operación en
 * absoluto. Justo por eso, filtro por precio (`price[gte]`/`price[lte]`),
 * orden (`orderBy[price]`, por defecto id ascendente) y paginación se
 * aplican A MANO al final de provide() — ninguna extensión estándar de API
 * Platform (que solo se activa sobre el provider Doctrine) llega a
 * intervenir aquí.
 *
 * Excepción: un paquete SIN ningún vínculo explícito paquete→producto
 * (CommunicationPackageProviderProduct) no se muestra aquí, aunque
 * PackageCatalogResolver haya resuelto un precio vía matching automático —
 * la app móvil solo debe ofrecer paquetes que el admin confirmó que
 * despachan de verdad. Este filtro es exclusivo de la vista móvil: el
 * dashboard (preview(), coverage()) sigue usando PackageCatalogResolver sin
 * este recorte, porque ahí el admin necesita ver el paquete PARA vincularlo.
 *
 * Los paquetes del mercado (COMR-275) son la otra excepción: su proveedor es el propio (OWN) y no tienen producto de proveedor,
 * así que cuentan como vinculados (CommunicationPackage::isMarket()) y MarketPackageEnricher les añade sus datos propios.
 *
 * Mismo criterio para ClientServiceProviderCoverageResolver: un cliente que
 * ya tiene routing configurado (ClientProviderRouting) solo ve paquetes de
 * los service/subservice que tiene explícitamente cubiertos — ver docblock
 * de esa clase.
 *
 * Tampoco se muestra un paquete fuera de su propia ventana activa
 * (CommunicationPackage::isActiveAt()) — PackageCatalogResolver::
 * offersFromContracts() no filtra por esto (solo mira si el CONTRATO está
 * vigente, no el paquete), así que un paquete de promoción futura cuyo
 * contrato ya esté vigente (ej. reutilizó el contrato "por defecto" del
 * catálogo normal al mismo monto — ver upsertContract()) podía colarse aquí
 * antes de tiempo. Ese mismo paquete SÍ debe aparecer en /upcoming
 * (UpcomingPackageCatalogResolver), nunca en los dos a la vez.
 */
final class CommunicationPackageCatalogProvider implements ProviderInterface
{
    public function __construct(
        private readonly PackageCatalogResolver $catalogResolver,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
        private readonly Security $security,
        private readonly BenefitOperationResolver $benefitResolver,
        private readonly ClientServiceProviderCoverageResolver $coverageResolver,
        private readonly ?MarketPackageEnricher $marketEnricher = null,
    ) {
    }

    /**
     * @return iterable<CommunicationPackage>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $tenant = $this->security->getUser();
        if (!$tenant instanceof Account) {
            return [];
        }

        $now = new \DateTimeImmutable();

        $packages = array_map(
            static fn (ResolvedPackageOffer $offer): CommunicationPackage => $offer->package,
            $this->catalogResolver->catalogFor($tenant, $now),
        );

        $boundIds = $this->bindingRepo->findPackageIdsWithBindings(
            array_map(static fn (CommunicationPackage $p) => (int) $p->getId(), $packages)
        );

        $visible = array_values(array_filter(
            $packages,
            fn (CommunicationPackage $p) => (in_array($p->getId(), $boundIds, true) || $p->isMarket())
                && $p->isActiveAt($now)
                && $this->coverageResolver->isCoveredFor($tenant, $p)
        ));

        // Resuelve `operation` (MULTIPLY/ADD/SET) en vivo contra el estado
        // ACTUAL del catálogo — nunca se persiste (ver BenefitOperationResolver).
        foreach ($visible as $package) {
            $package->setBenefits($this->benefitResolver->resolve($package));
        }

        /** @var Request|null $request */
        $request = $context['request'] ?? null;

        $visible = $this->filterByCategory($visible, $request);
        $visible = $this->filterByPriceRange($visible, $request);
        $visible = $this->sort($visible, $request);

        $page = $this->paginate($visible, $operation, $request);

        // Los datos propios del mercado solo se leen para los paquetes de la página que se devuelve.
        $this->marketEnricher?->enrich(is_array($page) ? $page : iterator_to_array($page, false), $now);

        return $page;
    }

    /**
     * `service`, `subservice` y `area` — el comprador acota lo que ya puede ver (el routing de su cuenta se aplicó antes,
     * así que el filtro nunca muestra algo que la cuenta no cubre). Cada uno es opcional, independiente y se compara sin
     * distinguir mayúsculas ni espacios laterales con `service.name`, `service.subservice.name` y `service.area.name`;
     * uno vacío se ignora. Sin ninguno, devuelve todo. Es el mismo vocabulario que ServiceCategoryKey.
     *
     * @param list<CommunicationPackage> $packages
     * @return list<CommunicationPackage>
     */
    private function filterByCategory(array $packages, ?Request $request): array
    {
        if ($request === null) {
            return $packages;
        }

        $wanted = [];
        foreach (['service' => ['name'], 'subservice' => ['subservice', 'name'], 'area' => ['area', 'name']] as $param => $path) {
            $value = $request->query->get($param);
            $value = is_string($value) ? mb_strtolower(trim($value)) : '';
            if ($value !== '') {
                $wanted[$param] = [$path, $value];
            }
        }
        if ($wanted === []) {
            return $packages;
        }

        return array_values(array_filter($packages, static function (CommunicationPackage $p) use ($wanted): bool {
            foreach ($wanted as [$path, $value]) {
                $actual = $p->getService();
                foreach ($path as $key) {
                    $actual = is_array($actual) ? ($actual[$key] ?? null) : null;
                }
                if (!is_string($actual) || mb_strtolower(trim($actual)) !== $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * `price[gte]`/`price[lte]` — el precio es resuelto por
     * PackageCatalogResolver (no una columna real), así que este filtro se
     * aplica a mano sobre el array ya visible, no vía #[ApiFilter] (que
     * necesitaría una columna Doctrine real para generar SQL).
     *
     * @param list<CommunicationPackage> $packages
     * @return list<CommunicationPackage>
     */
    private function filterByPriceRange(array $packages, ?Request $request): array
    {
        if ($request === null) {
            return $packages;
        }

        $priceParams = $request->query->all('price');
        $gte = isset($priceParams['gte']) && is_numeric($priceParams['gte']) ? (float) $priceParams['gte'] : null;
        $lte = isset($priceParams['lte']) && is_numeric($priceParams['lte']) ? (float) $priceParams['lte'] : null;

        if ($gte === null && $lte === null) {
            return $packages;
        }

        return array_values(array_filter($packages, static function (CommunicationPackage $p) use ($gte, $lte): bool {
            $price = $p->getAmount();
            if ($price === null) {
                return false;
            }
            if ($gte !== null && $price < $gte) {
                return false;
            }

            return !($lte !== null && $price > $lte);
        }));
    }

    /**
     * `orderBy[price]=asc|desc` — sin este parámetro (o con cualquier otro
     * valor), se ordena por id ascendente, mismo criterio por defecto que
     * findActiveCatalog()/findUpcoming() en CommunicationPackageRepository.
     *
     * @param list<CommunicationPackage> $packages
     * @return list<CommunicationPackage>
     */
    private function sort(array $packages, ?Request $request): array
    {
        $orderBy = $request?->query->all('orderBy') ?? [];
        $priceDirection = isset($orderBy['price']) && strtoupper((string) $orderBy['price']) === 'DESC' ? 'DESC' : null;

        if (isset($orderBy['price'])) {
            usort($packages, static function (CommunicationPackage $a, CommunicationPackage $b) use ($priceDirection): int {
                $cmp = ($a->getAmount() ?? 0.0) <=> ($b->getAmount() ?? 0.0);

                return $priceDirection === 'DESC' ? -$cmp : $cmp;
            });

            return $packages;
        }

        usort($packages, static fn (CommunicationPackage $a, CommunicationPackage $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));

        return $packages;
    }

    /**
     * Réplica manual de la paginación estándar de API Platform — este
     * provider bypasea el provider Doctrine (ver docblock de clase), así
     * que la extensión de paginación de la Query nunca llega a intervenir;
     * hay que envolver el resultado en ArrayPaginator (la misma clase que
     * usa el core) para que la colección salga con metadata Hydra
     * (hydra:view, totalItems) igual que cualquier otro endpoint paginado.
     *
     * @param list<CommunicationPackage> $packages
     */
    private function paginate(array $packages, Operation $operation, ?Request $request): iterable
    {
        if ($operation->getPaginationEnabled() === false || $request === null) {
            return $packages;
        }

        $itemsPerPage = $operation->getPaginationItemsPerPage() ?? 20;
        if ($operation->getPaginationClientItemsPerPage() === true) {
            $requested = $request->query->get('itemsPerPage');
            if (is_numeric($requested)) {
                $itemsPerPage = (int) $requested;
            }
        }

        $maxItemsPerPage = $operation->getPaginationMaximumItemsPerPage();
        if ($maxItemsPerPage !== null) {
            $itemsPerPage = min($itemsPerPage, $maxItemsPerPage);
        }
        $itemsPerPage = max(1, $itemsPerPage);

        $page = max(1, (int) $request->query->get('page', 1));
        $firstResult = ($page - 1) * $itemsPerPage;

        return new ArrayPaginator($packages, $firstResult, $itemsPerPage);
    }
}
