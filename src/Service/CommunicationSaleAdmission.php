<?php

namespace App\Service;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;

/**
 * Resultado de CommunicationSaleService::admitV2()/admitV2ForReserve() — lo
 * mínimo que processReserve()/processRecharge()/executeSale() necesitan
 * para terminar de construir la venta.
 */
final readonly class CommunicationSaleAdmission
{
    public function __construct(
        public string $provider,
        public float $amount,
        public string $currency,
        public ?CommunicationPackage $catalogPackage = null,
        public ?CommunicationProduct $dispatchProduct = null,
        public ?string $dispatchExternalRef = null,
        public ?float $destinationAmount = null,
        public ?string $destinationCurrency = null,
    ) {
    }
}
