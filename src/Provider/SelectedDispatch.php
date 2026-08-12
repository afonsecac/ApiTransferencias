<?php

namespace App\Provider;

use App\Entity\CommunicationProduct;
use App\Enums\CommunicationProviderEnum;

/**
 * Resultado de ProviderDispatchResolver::select() — el proveedor y producto
 * concretos elegidos para despachar la venta de un CommunicationPackage.
 */
final readonly class SelectedDispatch
{
    public function __construct(
        public CommunicationProviderEnum $provider,
        public CommunicationProduct $product,
        public string $externalRef,
    ) {
    }
}
