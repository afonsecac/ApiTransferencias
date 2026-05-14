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
}
