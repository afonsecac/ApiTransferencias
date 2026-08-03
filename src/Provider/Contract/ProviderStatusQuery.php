<?php

namespace App\Provider\Contract;

/**
 * Consulta de estado de una venta ya enviada al proveedor.
 */
final readonly class ProviderStatusQuery
{
    public function __construct(
        public string $transactionId,
        public ?string $providerReference = null,
    ) {
    }
}
