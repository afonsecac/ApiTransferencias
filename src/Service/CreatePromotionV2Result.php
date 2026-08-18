<?php

namespace App\Service;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Service\Pricing\ContractRangeResult;

/**
 * Resultado de CommunicationPromotionService::createV2() — la promoción,
 * los CommunicationPackage generados por rango (todos marcados con esta
 * promoción) y el resultado de los CommunicationContract "por defecto"
 * creados para ellos. NO incluye equivalencias por proveedor — eso es
 * Fase 5C/5D, todavía sin implementar.
 */
final readonly class CreatePromotionV2Result
{
    /**
     * @param list<CommunicationPackage> $packages
     */
    public function __construct(
        public CommunicationPromotions $promotion,
        public array $packages,
        public ContractRangeResult $contracts,
    ) {
    }
}
