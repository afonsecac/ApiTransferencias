<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

final class ProviderRoutingOutDto
{
    public int $id;
    public int $clientId;
    public ?string $clientName = null;
    public ?int $environmentId = null;
    public ?string $environmentType = null;
    public ?string $saleType = null;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE']])]
    public string $provider;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE'], 'nullable' => true])]
    public ?string $fallbackProvider = null;

    public bool $isActive = true;
    public ?string $notes = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
