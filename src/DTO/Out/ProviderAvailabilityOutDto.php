<?php

namespace App\DTO\Out;

use App\OpenApi\Attribute\OAProperty;

/**
 * Una fila de la matriz proveedor x entorno — ver
 * App\Service\Provider\ProviderAvailabilityService::statusMatrix().
 */
final class ProviderAvailabilityOutDto
{
    #[OAProperty(description: 'Código de proveedor (ETECSA, DTONE, CSQ)')]
    public string $provider;

    #[OAProperty(description: 'TEST o PROD')]
    public string $environmentType;

    #[OAProperty(description: 'Credenciales obligatorias completas para este entorno')]
    public bool $configured;

    #[OAProperty(description: 'Interruptor MANUAL (sys_config provider.{code}.{type}.active)')]
    public bool $manualActive;

    #[OAProperty(description: 'Estado AUTO — lo último que decidió el ping periódico')]
    public bool $autoEnabled;

    #[OAProperty(description: 'Despachable ahora mismo: global && configured && manual && auto')]
    public bool $effective;

    #[OAProperty(description: 'MANUAL o AUTO — origen del último cambio')]
    public ?string $lastActionType;

    #[OAProperty(description: 'Identificador del usuario que hizo el último cambio manual (null si fue AUTO)')]
    public ?string $lastActionBy;

    #[OAProperty(description: 'Fecha/hora ISO-8601 del último cambio')]
    public ?string $lastActionAt;

    #[OAProperty(description: 'Motivo del último cambio')]
    public ?string $lastActionReason;

    #[OAProperty(description: 'Fecha/hora ISO-8601 del último ping')]
    public ?string $lastPingAt;

    #[OAProperty(description: 'Resultado del último ping (null si fue inconclusive)')]
    public ?bool $lastPingSuccess;

    #[OAProperty(description: 'Latencia del último ping, en milisegundos')]
    public ?int $lastPingLatencyMs;

    #[OAProperty(description: 'Error del último ping, si lo hubo')]
    public ?string $lastPingError;
}
