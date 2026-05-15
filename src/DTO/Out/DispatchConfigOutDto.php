<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

final class DispatchConfigOutDto
{
    #[OAProperty(description: 'true si el dispatch a comunicaciones está activo, false si está pausado')]
    public bool $dispatchEnabled;

    #[OAProperty(description: 'Número de ventas persistidas pero pendientes de encolar (stateProcess=Created, state=Pending)')]
    public int $pendingCount;
}
