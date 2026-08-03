<?php

namespace App\Provider\DTOne;

use App\Enums\ProviderOutcomeEnum;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderStatusResult;

/**
 * Traduce las 8 clases de estado documentadas de DTOne
 * (https://developers.dtone.com/reference/gettransactionbyid) a
 * ProviderOutcomeEnum. El body de la respuesta de POST /v1/async/transactions
 * y de GET /v1/transactions?external_id= comparten la misma forma anidada:
 * `status: {id, message, class: {id, message}}` — `status.class.id` es la
 * clase general (las 8 documentadas, lo que hay que usar para decidir el
 * resultado, según la propia recomendación de DTOne: "define application
 * logic based on classes"); `status.id` es un código mucho más granular
 * (p.ej. 20000) que NO es uno de los 8 valores de clase — se conserva solo
 * como `providerCode` de diagnóstico, nunca para decidir el outcome.
 *
 * Confirmado en vivo el 2026-08-03: leer `status.id` en vez de
 * `status.class.id` hacía que CUALQUIER transacción real y exitosa de
 * DTOne (id real 20000, class.id 2 "CONFIRMED") cayera en el `default`
 * de abajo → UNKNOWN — la venta nunca completaba el ciclo hacia
 * COMPLETED/REJECTED, quedaba en Pending para siempre.
 *
 * REVERSED(8) es el caso especial: implica que la transacción ya estuvo
 * COMPLETED y el proveedor la revirtió después (p.ej. fraude, contracargo).
 * ProviderOutcomeEnum no modela un flujo de reembolso, así que mapearlo a
 * REJECTED sería incorrecto si la venta ya generó un débito real —
 * CommunicationSaleService::createSaleBalance() ya pudo haber corrido. Se
 * mapea a UNKNOWN a propósito: nunca re-despacha y fuerza revisión manual
 * en vez de reclasificar en silencio una venta que quizás ya cobró.
 */
final class DTOneStatusMapper
{
    public const STATUS_CREATED = 1;
    public const STATUS_CONFIRMED = 2;
    public const STATUS_REJECTED = 3;
    public const STATUS_CANCELLED = 4;
    public const STATUS_SUBMITTED = 5;
    public const STATUS_COMPLETED = 7;
    public const STATUS_REVERSED = 8;
    public const STATUS_DECLINED = 9;

    /**
     * @param array<string, mixed> $response
     */
    public function mapDispatch(array $response): ProviderDispatchResult
    {
        // ACCEPTED, no PENDING: es el resultado del despacho INICIAL — solo
        // ACCEPTED hace que CommunicationSaleService::invokeRechargeCommunication()
        // programe el re-chequeo diferido (CheckSaleMessage) que resuelve el
        // estado final más adelante. Mismo contrato que
        // EtecsaCommunicationProvider::recharge() (ver EtecsaStatusMapper).
        $outcome = $this->outcomeFor($response, ProviderOutcomeEnum::ACCEPTED);
        $status = $this->statusObject($response);

        return new ProviderDispatchResult(
            outcome: $outcome,
            providerReference: isset($response['id']) ? (string) $response['id'] : null,
            providerCode: $status !== null && isset($status['id']) ? (string) $status['id'] : null,
            message: $status['message'] ?? null,
            raw: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    public function mapStatusQuery(array $response): ProviderStatusResult
    {
        // Aquí sí PENDING: es una RE-consulta de estado (vía CheckSaleMessage
        // o el cron CheckStatusTask), no el despacho inicial — "sigue igual,
        // vuelve a preguntar más tarde", no "programa la primera consulta".
        $outcome = $this->outcomeFor($response, ProviderOutcomeEnum::PENDING);
        $status = $this->statusObject($response);

        return new ProviderStatusResult(
            outcome: $outcome,
            providerReference: isset($response['id']) ? (string) $response['id'] : null,
            providerCode: $status !== null && isset($status['id']) ? (string) $status['id'] : null,
            message: $status['message'] ?? null,
            raw: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function outcomeFor(array $response, ProviderOutcomeEnum $stillProcessingOutcome): ProviderOutcomeEnum
    {
        $status = $this->statusObject($response);
        $classId = $status !== null && isset($status['class']['id']) ? (int) $status['class']['id'] : null;

        return match ($classId) {
            self::STATUS_CREATED, self::STATUS_CONFIRMED, self::STATUS_SUBMITTED => $stillProcessingOutcome,
            self::STATUS_COMPLETED => ProviderOutcomeEnum::COMPLETED,
            self::STATUS_REJECTED, self::STATUS_CANCELLED, self::STATUS_DECLINED => ProviderOutcomeEnum::REJECTED,
            self::STATUS_REVERSED => ProviderOutcomeEnum::UNKNOWN,
            default => ProviderOutcomeEnum::UNKNOWN,
        };
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>|null
     */
    private function statusObject(array $response): ?array
    {
        if (!isset($response['status']) || !is_array($response['status'])) {
            return null;
        }

        return $response['status'];
    }
}
