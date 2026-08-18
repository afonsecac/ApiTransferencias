<?php

namespace App\Service\Pricing;

use App\Entity\CommunicationContract;

/**
 * Resultado de CommunicationContractService::upsertContract() — `isNew`
 * distingue "este paquete acaba de sumarse a un contrato (nuevo o
 * existente)" de "este paquete ya estaba en el contrato" (re-afirmación de
 * precio), para que los contadores created/updated de cada flujo de alta no
 * dependan de una consulta aparte.
 *
 * `contractIsNew` distingue, aparte, "la FILA de contrato se acaba de crear"
 * de "se reutilizó una fila existente" (aunque el paquete sea nuevo para
 * ella) — CommunicationContractService::applyPricing() lo usa para decidir
 * si puede fijar startAt/endAt libremente (contrato nuevo) o si debe
 * ensanchar en vez de reemplazar (contrato reutilizado): un contrato "por
 * defecto" indefinido no debe heredar el `endAt` corto de una promoción
 * que se fusiona en él, o el paquete que ya cubría también dejaría de
 * verse cuando la promoción termine.
 */
final readonly class UpsertContractResult
{
    public function __construct(
        public CommunicationContract $contract,
        public bool $isNew,
        public bool $contractIsNew,
    ) {
    }
}
