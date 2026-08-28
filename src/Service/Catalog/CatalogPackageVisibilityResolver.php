<?php

namespace App\Service\Catalog;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;

/**
 * Criterio de "¿este CommunicationPackage (V2) es visible para este tenant
 * ahora mismo?" — extraído de CommunicationPackageCatalogItemProvider para
 * poder reutilizarlo también desde CommunicationClientPackageItemProvider,
 * que delega aquí sobre la URL histórica `/communication/packages/{id}`.
 *
 * Un paquete que existe pero no es visible para este tenant (fuera de su
 * contrato), sin proveedor que lo cubra (UNAVAILABLE), fuera de su propia
 * ventana activa, o sin ningún vínculo explícito paquete→producto
 * (CommunicationPackageProviderProduct) se trata igual que "no
 * encontrado" — mismo criterio que CommunicationPackageCatalogProvider
 * aplica al listado, para no mostrar en el detalle algo que no aparece en
 * la colección.
 *
 * Mismo criterio para ClientServiceProviderCoverageResolver: un cliente que
 * ya tiene routing configurado (ClientProviderRouting) solo ve paquetes de
 * los service/subservice que tiene explícitamente cubiertos — ver docblock
 * de esa clase.
 */
class CatalogPackageVisibilityResolver
{
    public function __construct(
        private readonly PackageCatalogResolver $catalogResolver,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
        private readonly BenefitOperationResolver $benefitResolver,
        private readonly ClientServiceProviderCoverageResolver $coverageResolver,
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

        if (!$this->coverageResolver->isCoveredFor($tenant, $package)) {
            return null;
        }

        // Resuelve `operation` (MULTIPLY/ADD/SET) en vivo — nunca se
        // persiste, ver BenefitOperationResolver.
        $package->setBenefits($this->benefitResolver->resolve($package));

        return $package;
    }
}
