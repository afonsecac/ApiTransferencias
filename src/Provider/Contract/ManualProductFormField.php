<?php

namespace App\Provider\Contract;

/**
 * Describe un campo del formulario de alta manual de productos para un
 * proveedor concreto — lo que el dashboard necesita para dibujar el
 * formulario (GET /dashboard/api/products/manual/schema), nunca valores.
 * Mismo espíritu que ProviderConfigField para credenciales.
 */
final readonly class ManualProductFormField
{
    /**
     * @param 'text'|'number'|'date'|'select'|'toggle' $type
     * @param list<string> $options lista de valores válidos, solo para type='select'
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required = true,
        public array $options = [],
        public mixed $default = null,
        public ?string $help = null,
    ) {
    }
}
