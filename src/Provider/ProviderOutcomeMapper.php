<?php

namespace App\Provider;

use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderOutcomeEnum;

/**
 * Traduce un ProviderOutcomeEnum (resultado normalizado de cualquier
 * proveedor) al par (CommunicationStateEnum, stateProcess) que usa el resto
 * del sistema. Punto único de esta regla — antes repartida en el else-if de
 * CommunicationSaleService::checkStatusOrder().
 *
 * Regla crítica: UNKNOWN jamás produce stateProcess=CREATED. Si se hiciera,
 * el mensaje se reenviaría al proveedor y podría cobrar dos veces la misma
 * operación tras un timeout que sí llegó a procesarse del otro lado.
 */
final class ProviderOutcomeMapper
{
    public function toState(ProviderOutcomeEnum $outcome): CommunicationStateEnum
    {
        return match ($outcome) {
            ProviderOutcomeEnum::ACCEPTED,
            ProviderOutcomeEnum::PENDING,
            ProviderOutcomeEnum::RETRYABLE,
            ProviderOutcomeEnum::UNKNOWN => CommunicationStateEnum::PENDING,
            ProviderOutcomeEnum::COMPLETED => CommunicationStateEnum::COMPLETED,
            ProviderOutcomeEnum::REJECTED => CommunicationStateEnum::REJECTED,
            ProviderOutcomeEnum::FAILED => CommunicationStateEnum::FAILED,
        };
    }

    public function toStateProcess(ProviderOutcomeEnum $outcome): string
    {
        return match ($outcome) {
            ProviderOutcomeEnum::RETRYABLE => CommunicationStateEnum::CREATED->value,
            ProviderOutcomeEnum::COMPLETED => CommunicationStateEnum::COMPLETED->value,
            ProviderOutcomeEnum::REJECTED => CommunicationStateEnum::REJECTED->value,
            ProviderOutcomeEnum::FAILED => CommunicationStateEnum::FAILED->value,
            ProviderOutcomeEnum::ACCEPTED,
            ProviderOutcomeEnum::PENDING,
            ProviderOutcomeEnum::UNKNOWN => CommunicationStateEnum::PENDING->value,
        };
    }

    /**
     * Si tras este outcome corresponde programar un CheckSaleMessage: solo
     * cuando la venta sigue viva y en curso (ACCEPTED/PENDING/UNKNOWN).
     */
    public function shouldScheduleCheck(ProviderOutcomeEnum $outcome): bool
    {
        return in_array($outcome, [
            ProviderOutcomeEnum::ACCEPTED,
            ProviderOutcomeEnum::PENDING,
            ProviderOutcomeEnum::UNKNOWN,
        ], true);
    }

    public function isTerminal(ProviderOutcomeEnum $outcome): bool
    {
        return in_array($outcome, [
            ProviderOutcomeEnum::COMPLETED,
            ProviderOutcomeEnum::REJECTED,
            ProviderOutcomeEnum::FAILED,
        ], true);
    }
}
