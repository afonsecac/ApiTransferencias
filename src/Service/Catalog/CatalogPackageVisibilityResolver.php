<?php

namespace App\Service\Catalog;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;

/**
 * Criterio de "¿este CommunicationPackage (V2) es visible para este tenant
 * ahora mismo?" — extraído de CommunicationPackageCatalogItemProvider para
 * poder reutilizarlo también desde CommunicationClientPackageItemProvider
 * (V1) cuando CatalogVersionResolver::isV2() delega en el catálogo V2 sobre
 * la URL histórica `/communication/packages/{id}`.
 *
 * Un paquete que existe pero no es visible para este tenant (fuera de su
 * contrato), sin proveedor que lo cubra (UNAVAILABLE), fuera de su propia
 * ventana activa, o sin ningún vínculo explícito paquete→producto
 * (CommunicationPackageProviderProduct) se trata igual que "no
 * encontrado" — mismo criterio que CommunicationPackageCatalogProvider
 * aplica al listado, para no mostrar en el detalle algo que no aparece en
 * la colección.
 */
class CatalogPackageVisibilityResolver
{
    public function __construct(
        private readonly PackageCatalogResolver $catalogResolver,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
    ) {
    }

    public function visibleFor(CommunicationPackage $package, Account $tenant, ?\DateTimeImmutable $now = null): ?CommunicationPackage
    {
        $now ??= new \DateTimeImmutable();

        if (!$package->isActiveAt($now)) {
            return null;
        }

        $offer = $this->catalogResolver->offerFor($package, $tenant, $now);
        if ($offer === null || $offer->source === PackageOfferSourceEnum::UNAVAILABLE) {
            return null;
        }

        if ($this->bindingRepo->findAllForPackage($package) === []) {
            return null;
        }

        return $package;
    }
}
