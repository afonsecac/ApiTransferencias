<?php

namespace App\Provider\Contract;

/**
 * Petición neutral de venta de paquete, independiente del proveedor.
 */
final readonly class PackageSaleRequest
{
    public function __construct(
        public string $transactionId,
        public string $productExternalId,
        public ?string $productKind = null,
        public ?string $phoneNumber = null,
        public ?PackageCustomer $customer = null,
        public ?PackageSalePoint $salePoint = null,
    ) {
    }
}
