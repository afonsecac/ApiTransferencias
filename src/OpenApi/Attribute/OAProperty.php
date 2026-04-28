<?php

namespace App\OpenApi\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OAProperty
{
    /**
     * @param array|null  $schema      Schema OpenAPI completo (sobreescribe la inferencia automática)
     * @param string|null $description Descripción del campo (se añade al schema inferido o al override)
     */
    public function __construct(
        public readonly ?array $schema = null,
        public readonly ?string $description = null,
    ) {}
}
