<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

final class DispatchPendingOutDto
{
    #[OAProperty(description: 'Recargas encoladas en esta operación')]
    public int $recharges;

    #[OAProperty(description: 'Ventas de paquete encoladas en esta operación')]
    public int $packages;

    #[OAProperty(description: 'Total de mensajes encolados')]
    public int $total;

    /** @var array<string, int> */
    #[OAProperty(description: 'Ventas saltadas por proveedor no despachable, agrupadas por "PROVIDER|ENVIRONMENT_TYPE"')]
    public array $skipped;

    #[OAProperty(description: 'Ventas en espera de proveedor a las que se les pudo asignar uno en esta operación (no suman a total)')]
    public int $awaitingResolved;
}
