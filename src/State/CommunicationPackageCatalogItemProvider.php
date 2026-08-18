<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Service\Catalog\CatalogPackageVisibilityResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * GET /communication/packages/catalog/{id} — análogo a
 * CommunicationClientPackageItemProvider (V1): decora el item provider
 * Doctrine por defecto y delega el criterio de visibilidad en
 * CatalogPackageVisibilityResolver (compartido con la delegación V1→V2 de
 * `/communication/packages/{id}` para cuentas V2 — ver
 * CommunicationClientPackageItemProvider).
 */
final class CommunicationPackageCatalogItemProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly CatalogPackageVisibilityResolver $visibility,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CommunicationPackage
    {
        $package = $this->itemProvider->provide($operation, $uriVariables, $context);
        if (!$package instanceof CommunicationPackage) {
            return null;
        }

        $tenant = $this->security->getUser();
        if (!$tenant instanceof Account) {
            return null;
        }

        return $this->visibility->visibleFor($package, $tenant);
    }
}
