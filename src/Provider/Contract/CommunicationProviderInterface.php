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

    /**
     * Esquema de configuración de este proveedor: qué claves necesita
     * (nombre, si son obligatorias, si se cifran) para poder operar. Lo lee
     * ProviderCredentialsResolver para saber qué filas de sys_config buscar
     * y ProviderCredentialsAdminService para construir la pantalla de
     * configuración — cada proveedor declara el suyo, no hay un esquema
     * fijo común (ver ProviderConfigField).
     *
     * @return list<ProviderConfigField>
     */
    public function getConfigSchema(): array;
}
