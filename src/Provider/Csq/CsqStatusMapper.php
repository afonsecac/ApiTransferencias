<?php

namespace App\Provider\Csq;

use App\Enums\ProviderOutcomeEnum;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderStatusResult;

/**
 * Traduce las respuestas crudas de CSQ (Purchase / Transaction Info) a los
 * DTOs neutrales de la abstracción de proveedor.
 *
 * `finalstatus` NO se usa para decidir el resultado — es ruido, confirmado
 * en vivo el 2026-08-10: en el único ejemplo de éxito documentado por CSQ
 * vale `0` con `resultcode:"10"`, pero en una compra real y exitosa valió
 * `10` (igual al resultcode), y en un fallo real distinto valió `-1`
 * mientras el resultcode era `"927"`. Sin relación fija. La única señal
 * fiable, confirmada en 1 éxito + 3 fallos reales, es `rc` a nivel raíz:
 * `0` = éxito, cualquier otro valor = fallo — consistente en los 4 casos.
 *
 * mapDispatch() y mapStatusQuery() NO comparten la misma regla para
 * rc!==0 — ver el docblock de mapStatusQuery() para la asimetría y por qué
 * es deliberada.
 */
final class CsqStatusMapper
{
    /**
     * POST /pre-paid/recharge/purchase es síncrono: la respuesta ya es el
     * resultado final de ESTA compra (no "aceptada, confirmo después" como
     * DTOne/ETECSA) — así que un `rc!==0` aquí es un rechazo confirmado por
     * CSQ sobre la transacción que nosotros mismos acabamos de intentar:
     * confiable, se mapea a REJECTED directo (nunca reintenta el envío, la
     * decisión de "no cobrar" ya es segura).
     *
     * @param array<string, mixed> $response
     */
    public function mapDispatch(array $response): ProviderDispatchResult
    {
        $item = $this->firstItem($response);
        $rc = (int) ($response['rc'] ?? -1);

        return new ProviderDispatchResult(
            outcome: $rc === 0 ? ProviderOutcomeEnum::COMPLETED : ProviderOutcomeEnum::REJECTED,
            providerReference: $this->stringOrNull($item['supplierreference'] ?? null),
            providerCode: $this->stringOrNull($item['resultcode'] ?? null),
            message: $this->stringOrNull($item['resultmessage'] ?? null),
            raw: $response,
        );
    }

    /**
     * GET /util/transaction/info tiene delay de indexación (confirmado en
     * vivo, 2026-08-10): consultado segundos después de crear la
     * transacción responde `rc:-1, resultcode:"900", "Invalid parameters"`
     * — igual para una transacción real recién creada que para una
     * inexistente. Consultado minutos/horas después contra esas MISMAS
     * transacciones reales, respondió correctamente (`rc:0,
     * resultcode:"10"`, con el `supplierreference` de la compra original).
     * No es un endpoint roto, tiene latencia — no se caracterizó cuánta.
     *
     * Un `resultcode="900"` (u otro `rc!==0` mientras el delay no pasó)
     * describe un fallo en NUESTRA petición de consulta en ESE momento, no
     * necesariamente el estado real de la transacción original — a
     * diferencia de mapDispatch(), no es una señal confiable de rechazo
     * inmediato. Mapear esto a REJECTED marcaría como rechazada (sin
     * cobrar/acreditar en nuestro sistema) una venta que en realidad sí se
     * completó del lado de CSQ, solo que aún no indexada — un desajuste
     * real de saldo. Por eso cualquier `rc!==0` aquí cae a UNKNOWN: no
     * reclasifica en silencio, deja que un reintento posterior (vía
     * CheckStatusTask, que ya sabe reconsultar con backoff) resuelva el
     * estado real una vez CSQ termine de indexar.
     *
     * @param array<string, mixed> $response
     */
    public function mapStatusQuery(array $response): ProviderStatusResult
    {
        $item = $this->firstItem($response);
        $rc = (int) ($response['rc'] ?? -1);

        return new ProviderStatusResult(
            outcome: $rc === 0 ? ProviderOutcomeEnum::COMPLETED : ProviderOutcomeEnum::UNKNOWN,
            providerReference: $this->stringOrNull($item['supplierreference'] ?? null),
            providerCode: $this->stringOrNull($item['resultcode'] ?? null),
            message: $this->stringOrNull($item['resultmessage'] ?? null),
            raw: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function firstItem(array $response): array
    {
        $items = $response['items'] ?? [];

        return is_array($items) && isset($items[0]) && is_array($items[0]) ? $items[0] : [];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
