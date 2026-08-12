<?php

namespace App\Service\Pricing;

/**
 * Resultado de CommunicationContractService::createByRange(). A diferencia
 * de ContractBatchResult (que varía por CLIENTE), este varía por PAQUETE:
 * `skipped`/`skippedAmounts` reportan los montos del rango que no tenían
 * ningún CommunicationPackage correspondiente — nunca se omiten en
 * silencio, el admin necesita ver los huecos para corregir el catálogo.
 */
final readonly class ContractRangeResult
{
    /**
     * @param int[]   $contractIds
     * @param float[] $skippedAmounts
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public array $contractIds,
        public array $skippedAmounts,
    ) {
    }
}
