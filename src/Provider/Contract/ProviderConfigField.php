<?php

namespace App\Provider\Contract;

/**
 * Un campo de configuración que un proveedor concreto necesita para operar
 * (p.ej. `base_url`, `api_key`, `username`). Cada adaptador declara su propio
 * conjunto vía `CommunicationProviderInterface::getConfigSchema()` — no
 * existe un esquema fijo común a todos los proveedores, porque cada uno
 * necesita datos distintos con nombres y semántica propios.
 */
final readonly class ProviderConfigField
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $required,
        public bool $secret,
    ) {
    }
}
