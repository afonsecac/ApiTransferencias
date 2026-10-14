<?php

namespace App\Provider\Contract;

use App\Enums\ProviderOutcomeEnum;

/**
 * Resultado neutral del envío de una recarga o venta de paquete a un proveedor.
 * `raw` conserva la respuesta cruda tal cual, para persistir en
 * CommunicationSaleInfo::transactionStatus (histórico/depuración).
 */
final readonly class ProviderDispatchResult
{
    /**
     * `providerCode` de un envío que el proveedor rechazó por NUESTRA credencial (401/403:
     * caducada, rotada o sin permisos), no por la venta. Va con RETRYABLE: la transacción nunca
     * se creó, así que ni se rechaza la venta ni se cambia de proveedor, y reintentar el envío es seguro.
     */
    public const CODE_CREDENTIALS_REJECTED = 'PROVIDER_CREDENTIALS_REJECTED';

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public ProviderOutcomeEnum $outcome,
        public ?string $providerReference = null,
        public ?string $providerCode = null,
        public ?string $message = null,
        public array $raw = [],
    ) {
    }
}
