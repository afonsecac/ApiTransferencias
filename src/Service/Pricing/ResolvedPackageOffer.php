<?php

namespace App\Service\Pricing;

use App\Entity\CommunicationPackage;

/**
 * Resultado de PackageCatalogResolver (Fase 2) para un CommunicationPackage
 * concreto — análogo a ResolvedSalePrice pero para el catálogo agnóstico de
 * proveedor. `package` referencia la fila resuelta (relevante en
 * `catalogFor()`, donde el catálogo visible no es necesariamente "todos los
 * paquetes").
 */
final readonly class ResolvedPackageOffer
{
    public function __construct(
        public CommunicationPackage $package,
        public float $price,
        public string $currency,
        public PackageOfferSourceEnum $source,
        public ?int $contractId = null,
        public ?string $note = null,
    ) {
    }
}
