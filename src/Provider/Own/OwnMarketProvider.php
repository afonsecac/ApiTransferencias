<?php

namespace App\Provider\Own;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderDomainEnum;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderDomainAwareInterface;
use App\Provider\Contract\ProviderHealthCheckInterface;
use App\Provider\Contract\ProviderPingResult;

/**
 * Proveedor propio del mercado (ADR-001): la propia plataforma como proveedor de sus productos. Mínimo a
 * propósito: opera solo en el dominio de mercado, no necesita configuración y no tiene ninguna capacidad de
 * comunicaciones. Como no declara el dominio de comunicaciones, ProviderRegistry::registered() no lo lista y
 * ninguna pantalla, tarea ni resolver de comunicaciones lo ve ni lo elige.
 *
 * Con esquema de configuración vacío ProviderCredentialsResolver::isFullyConfigured() siempre da true. El
 * HealthCheck es trivial: un adaptador sin HealthCheck produce «inconclusive» y avisaría a los
 * administradores en cada ciclo si algún día entrara en el ping.
 */
final class OwnMarketProvider implements ProviderDomainAwareInterface, ProviderHealthCheckInterface
{
    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::OWN;
    }

    /**
     * @return list<ProviderDomainEnum>
     */
    public function getDomains(): array
    {
        return [ProviderDomainEnum::Market];
    }

    public function getCapabilities(): array
    {
        return [];
    }

    public function getConfigSchema(): array
    {
        return [];
    }

    public function checkHealth(ProviderContext $context): ProviderPingResult
    {
        return ProviderPingResult::available();
    }
}
