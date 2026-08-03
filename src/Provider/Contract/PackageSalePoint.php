<?php

namespace App\Provider\Contract;

/**
 * Punto de venta (oficina comercial/provincia) para una venta de paquete.
 */
final readonly class PackageSalePoint
{
    public function __construct(
        public ?int $commercialOfficeExternalId = null,
        public ?int $provinceExternalId = null,
        public ?bool $isAirport = null,
    ) {
    }
}
