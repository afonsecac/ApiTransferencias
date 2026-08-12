<?php

namespace App\Service\Pricing;

/**
 * Resultado de PackageContractService::createByRate() — mismo espíritu que
 * CommunicationPromotionService::createPackagesForPromotion() (que solo
 * devuelve un int), pero exponiendo el detalle que la pantalla de contratos
 * necesita mostrar tras un alta masiva.
 */
final readonly class ContractBatchResult
{
    /**
     * @param int[] $accountIds
     */
    public function __construct(
        public int $created,
        public int $updated,
        public array $accountIds,
    ) {
    }
}
