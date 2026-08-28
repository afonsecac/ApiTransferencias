<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

/**
 * Impacto de un cambio de routing ANTES de aplicarlo. `affectedPackagesCount`
 * queda en null hasta la Fase 3 (communication_product.provider) — hoy no
 * hay forma de saber a qué proveedor pertenece cada CommunicationProduct.
 */
final class ProviderRoutingPreviewOutDto
{
    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE']], description: 'Proveedor efectivo HOY para este cliente/entorno/tipo de venta')]
    public string $currentEffectiveProvider;

    #[OAProperty(schema: ['type' => 'string', 'enum' => ['ETECSA', 'DTONE']], description: 'Proveedor efectivo que resultaría tras aplicar el cambio propuesto')]
    public string $proposedEffectiveProvider;

    #[OAProperty(description: 'Ventas del cliente en estado Pending (cualquier proveedor). Verificar antes de reasignar')]
    public int $pendingSalesCount = 0;

    #[OAProperty(description: 'Si el proveedor propuesto no está registrado en el sistema')]
    public bool $proposedProviderUnregistered = false;

    #[OAProperty(description: 'Reservado para la Fase 3: paquetes del cliente que quedarían sin proveedor equivalente. null = no calculable todavía')]
    public ?int $affectedPackagesCount = null;

    #[OAProperty(description: 'Paquetes del catálogo, hoy visibles para este cliente, que dejarían de verse si se guarda este enrutado — ver ClientCatalogVisibilityImpactResolver')]
    public int $newlyHiddenPackagesCount = 0;
}
