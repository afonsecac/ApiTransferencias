<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
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
 */
class CommunicationClientPackageItemProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly Security $security,
        private readonly PackageSalePriceResolver $salePriceResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $package = $this->itemProvider->provide($operation, $uriVariables, $context);

        $tenant = $this->security->getUser();
        if ($package instanceof CommunicationClientPackage && $tenant instanceof Account) {
            $package->setResolvedSalePrice($this->salePriceResolver->resolve($package, $tenant));
        }

        return $package;
    }
}
