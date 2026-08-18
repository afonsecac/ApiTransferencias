<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Repository\CommunicationPackageRepository;
use App\Service\Catalog\CatalogPackageVisibilityResolver;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\Pricing\PackageSalePriceResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decora el item provider por defecto de GET /communication/packages/{id}
 * para que también pase por PackageSalePriceResolver — antes de este
 * rediseño el detalle de un paquete no resolvía ningún precio (solo el
 * listado colección lo hacía a través de
 * CommunicationClientPackageProvider), así que un GET por id podía mostrar
 * un importe distinto al que veía el mismo cliente en el listado.
 *
 * Switch V2: para cuentas marcadas V2 (CatalogVersionResolver::isV2()), el
 * id ya no pertenece al espacio de CommunicationClientPackage — no se puede
 * delegar en el item provider Doctrine decorado (resolvería la clase
 * equivocada, porque $operation sigue apuntando a CommunicationClientPackage),
 * así que se busca el CommunicationPackage directamente por repositorio y
 * se aplica el mismo criterio de visibilidad que /communication/packages/catalog/{id}
 * (CatalogPackageVisibilityResolver).
 */
class CommunicationClientPackageItemProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly Security $security,
        private readonly PackageSalePriceResolver $salePriceResolver,
        private readonly CatalogVersionResolver $catalogVersion,
        private readonly CommunicationPackageRepository $packageRepository,
        private readonly CatalogPackageVisibilityResolver $visibility,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $tenant = $this->security->getUser();

        if ($tenant instanceof Account && $this->catalogVersion->isV2($tenant)) {
            $id = $uriVariables['id'] ?? null;
            if (!is_numeric($id)) {
                return null;
            }
            $package = $this->packageRepository->find((int) $id);

            return $package === null ? null : $this->visibility->visibleFor($package, $tenant);
        }

        $package = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($package instanceof CommunicationClientPackage && $tenant instanceof Account) {
            $package->setResolvedSalePrice($this->salePriceResolver->resolve($package, $tenant));
        }

        return $package;
    }
}
