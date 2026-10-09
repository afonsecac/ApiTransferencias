<?php

namespace App\DTO\Out;

final class SaleRetryOutDto
{
    public int $id;
    public string $state;
    public bool $retryDispatched;

    // Solo presentes cuando el reintento pasó por EtecsaRetryService
    // (POST /sale/retry) — null para el resto de proveedores/paquetes, que
    // conservan el flujo de reenvío ciego de siempre.
    public ?string $action = null;
    public ?string $message = null;
    public ?string $previousStatus = null;
}
