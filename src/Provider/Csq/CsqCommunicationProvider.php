<?php

namespace App\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\Contract\ProviderContext;

/**
 * Adaptador de CSQ ("eVSB") a la abstracción de proveedor. Implementa
 * únicamente el contrato base — sin RECHARGE/PACKAGE_SALE/BALANCE/CATALOG —
 * porque la especificación pública de CSQ (https://csq-docs.apidog.io) solo
 * cubre el esquema de autenticación y un health-check (/ping); los métodos
 * de negocio reales se entregan por contrato/NDA y todavía no se conocen.
 *
 * Queda registrado igualmente (tag app.communication_provider vía
 * _instanceof en config/services.yaml) para que aparezca en
 * `GET /admin/providers` y en la pantalla de configuración de proveedores
 * del dashboard — así se pueden cargar y probar sus credenciales desde ya,
 * aunque todavía no participe en el enrutado real de ventas.
 */
final class CsqCommunicationProvider implements CommunicationProviderInterface
{
    public function __construct(
        private readonly CsqHttpClient $client,
    ) {
    }

    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::CSQ;
    }

    /**
     * @return list<ProviderCapabilityEnum>
     */
    public function getCapabilities(): array
    {
        return [];
    }

    /**
     * @return list<ProviderConfigField>
     */
    public function getConfigSchema(): array
    {
        return [
            new ProviderConfigField('base_url', 'URL base', required: true, secret: false),
            new ProviderConfigField('username', 'Usuario (U)', required: true, secret: true),
            new ProviderConfigField('password', 'Password', required: true, secret: true),
            new ProviderConfigField('terminal', 'Terminal ID', required: true, secret: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ping(ProviderContext $context, string $echo = 'pong'): array
    {
        return $this->client->ping($context, $echo);
    }
}
