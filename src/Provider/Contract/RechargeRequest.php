<?php

namespace App\Provider\Contract;

/**
 * Petición neutral de recarga de saldo, independiente del proveedor.
 */
final readonly class RechargeRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $transactionId,
        public ?string $phoneNumber,
        public string $productExternalId,
        public float $destinationAmount,
        public string $destinationUnit,
        public ?float $sendAmount = null,
        public array $metadata = [],
        /**
         * Identificador de destino tipo cuenta (no un número de teléfono) —
         * p.ej. la cuenta Nauta para Nauta WIFI Recharge. Ver
         * CommunicationProduct::$requiredIdentifierFields: algunos
         * productos exigen SOLO este, otros SOLO phoneNumber, y algunos
         * (Nauta Hogar Plus) exigen ambos a la vez.
         */
        public ?string $accountIdentifier = null,
    ) {
    }
}
