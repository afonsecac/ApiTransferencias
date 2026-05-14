<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

final class SysConfigOutDto
{
    public int $id;

    #[OAProperty(description: 'Clave única de la variable')]
    public string $propertyName;

    #[OAProperty(description: 'Valor de la variable')]
    public string $propertyValue;

    #[OAProperty(description: 'Indica si la variable está activa')]
    public ?bool $isActive = null;

    #[OAProperty(schema: ['type' => 'array', 'items' => ['type' => 'integer'], 'nullable' => true], description: 'IDs de clientes a los que aplica. null significa que aplica a todos')]
    public ?array $clients = null;

    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    #[OAProperty(description: 'Fecha de borrado lógico. null si la variable sigue activa')]
    public ?string $removedAt = null;
}
