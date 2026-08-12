<?php

namespace App\Service;

use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;

/**
 * Resultado de CommunicationSaleService::admit() — lo mínimo que
 * processReserve()/processRecharge()/executeSale() necesitan para terminar
 * de construir la venta, sin importar si vino de la rama legacy o V2.
 * `legacyPackage !== null` XOR `catalogPackage !== null`: nunca ambos, nunca
 * ninguno — ver isV2().
 */
final readonly class CommunicationSaleAdmission
{
    public function __construct(
        public string $provider,
        public float $amount,
        public string $currency,
        public ?CommunicationClientPackage $legacyPackage = null,
        public ?CommunicationPackage $catalogPackage = null,
        public ?CommunicationProduct $dispatchProduct = null,
        public ?string $dispatchExternalRef = null,
        public ?float $destinationAmount = null,
        public ?string $destinationCurrency = null,
    ) {
    }

    public function isV2(): bool
    {
        return $this->catalogPackage !== null;
    }
}
