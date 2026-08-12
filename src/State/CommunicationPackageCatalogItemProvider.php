<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * GET /communication/packages/catalog/{id} — análogo a
 * CommunicationClientPackageItemProvider (V1): decora el item provider
 * Doctrine por defecto y resuelve la oferta con PackageCatalogResolver, que
 * decide visibilidad además de precio. Un paquete que existe pero no es
 * visible para este tenant (fuera de su contrato), sin proveedor que lo
 * cubra (UNAVAILABLE), o sin ningún vínculo explícito paquete→producto
 * (CommunicationPackageProviderProduct) se trata igual que "no
 * encontrado" — mismo criterio de exclusión que
 * PackageCatalogResolver::catalogFor()/CommunicationPackageCatalogProvider
 * aplican al listado, para no mostrar en el detalle algo que no aparece en
 * la colección.
 */
final class CommunicationPackageCatalogItemProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly PackageCatalogResolver $catalogResolver,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
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

        $offer = $this->catalogResolver->offerFor($package, $tenant);
        if ($offer === null || $offer->source === PackageOfferSourceEnum::UNAVAILABLE) {
            return null;
        }

        if ($this->bindingRepo->findAllForPackage($package) === []) {
            return null;
        }

        return $package;
    }
}
