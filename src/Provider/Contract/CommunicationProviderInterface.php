<?php

namespace App\Provider\Contract;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;

/**
 * Marca común de todo adaptador de proveedor externo de recargas/paquetes.
 * Implementada por App\Provider\{Etecsa,DTOne}\*CommunicationProvider y
 * descubierta automáticamente por ProviderRegistry vía el tag
 * 'app.communication_provider' (ver _instanceof en config/services.yaml).
 */
interface CommunicationProviderInterface
{
    public function getCode(): CommunicationProviderEnum;

    /**
     * @return list<ProviderCapabilityEnum>
     */
    public function getCapabilities(): array;
}
