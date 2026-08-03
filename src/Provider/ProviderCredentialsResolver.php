<?php

namespace App\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Repository\SysConfigRepository;

/**
 * Credenciales de proveedor almacenadas en sys_config con el esquema
 * `provider.{code}.{type}.{campo}` (campos según lo que declare cada
 * adaptador en `getConfigSchema()` — no hay un esquema fijo común: ETECSA
 * solo necesita base_url+api_key, DTOne necesita también api_secret, CSQ
 * necesita username+password en vez de una sola key). base_url nunca se
 * cifra; el resto se cifra según `ProviderConfigField::$secret` — mismo
 * mecanismo que el resto de sys_config.
 *
 * El fallback a las claves legacy de ETECSA (api.{type}.communications.key,
 * Environment::getBasePath()) vive en EtecsaGatewayClient, no aquí: este
 * resolver solo sabe leer lo que el proveedor declaró en su esquema.
 */
final class ProviderCredentialsResolver
{
    public function __construct(
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly ProviderRegistry $registry,
    ) {
    }

    /**
     * @return array<string, ?string> clave del campo (según getConfigSchema())
     *   => valor ya descifrado si aplica, o null si no está configurada
     */
    public function get(CommunicationProviderEnum $provider, string $environmentType): array
    {
        $prefix = $this->keyPrefix($provider, $environmentType);

        $values = [];
        foreach ($this->registry->get($provider)->getConfigSchema() as $field) {
            $values[$field->key] = $this->sysConfigRepo->findCachedValue($prefix . $field->key, mustBeActive: true);
        }

        return $values;
    }

    /**
     * true si todos los campos marcados como `required` en el esquema del
     * proveedor tienen un valor no vacío para este entorno. Es una de las
     * dos condiciones del gate de habilitación (ver isEnabled()) — no
     * considera el interruptor manual (isActive()).
     */
    public function isFullyConfigured(CommunicationProviderEnum $provider, string $environmentType): bool
    {
        $values = $this->get($provider, $environmentType);

        foreach ($this->registry->get($provider)->getConfigSchema() as $field) {
            if ($field->required && (($values[$field->key] ?? '') === '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Interruptor manual `provider.{code}.{type}.active` — independiente del
     * esquema de cada proveedor (no es un campo de getConfigSchema(), es una
     * bandera administrativa cruzada a todos). Por defecto true (activo) si
     * nunca se ha tocado, para no romper proveedores ya configurados antes
     * de que existiera este interruptor.
     */
    public function isActive(CommunicationProviderEnum $provider, string $environmentType): bool
    {
        $value = $this->sysConfigRepo->findCachedValue($this->activeKey($provider, $environmentType), mustBeActive: true);

        return $value === null || $value === '' || $value === '1';
    }

    /**
     * Gate real de habilitación que usa ProviderRoutingAdminService: el
     * proveedor debe tener sus claves obligatorias cargadas Y no estar
     * apagado manualmente para ese entorno.
     */
    public function isEnabled(CommunicationProviderEnum $provider, string $environmentType): bool
    {
        return $this->isFullyConfigured($provider, $environmentType) && $this->isActive($provider, $environmentType);
    }

    private function keyPrefix(CommunicationProviderEnum $provider, string $environmentType): string
    {
        return sprintf('provider.%s.%s.', strtolower($provider->value), strtolower($environmentType));
    }

    private function activeKey(CommunicationProviderEnum $provider, string $environmentType): string
    {
        return $this->keyPrefix($provider, $environmentType) . 'active';
    }
}
