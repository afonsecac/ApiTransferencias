<?php

namespace App\Service\Pricing;

use App\Entity\CommunicationContract;

/**
 * Resultado de CommunicationContractService::upsertContract() — `isNew`
 * distingue "este paquete acaba de sumarse a un contrato (nuevo o
 * existente)" de "este paquete ya estaba en el contrato" (re-afirmación de
 * precio), para que los contadores created/updated de cada flujo de alta no
 * dependan de una consulta aparte.
 */
final readonly class UpsertContractResult
{
    public function __construct(
        public CommunicationContract $contract,
        public bool $isNew,
    ) {
    }
}
