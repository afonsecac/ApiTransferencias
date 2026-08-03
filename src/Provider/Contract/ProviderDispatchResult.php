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
