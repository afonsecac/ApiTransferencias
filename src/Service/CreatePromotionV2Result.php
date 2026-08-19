<?php

namespace App\Service;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPromotions;
use App\Service\Pricing\ContractRangeResult;
use App\Service\Pricing\PromotionEquivalenceResult;

/**
 * Resultado de CommunicationPromotionService::createV2() — la promoción,
 * los CommunicationPackage generados por rango (todos marcados con esta
 * promoción), el resultado de los CommunicationContract "por defecto"
 * creados para ellos, cuántos contratos propios de tenants existentes se
 * vincularon a esos paquetes (ver CommunicationContractService::
 * linkTenantContractsToPromotionPackages()), y el reporte del
 * auto-poblado de equivalencias por proveedor (Fase 5D) — qué
 * proveedores cubrieron cuántos tramos, y qué huecos quedaron (ver
 * PromotionEquivalenceResult::$gaps) para que el admin los cure a mano.
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
        public int $tenantContractsLinked,
        public PromotionEquivalenceResult $equivalences,
    ) {
    }
}
