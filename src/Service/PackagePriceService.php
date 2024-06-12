<?php

namespace App\Service;

use App\Entity\CommunicationPrice;
use App\Entity\CommunicationPricePackage;
use App\Service\CommonService;

class PackagePriceService extends CommonService
{
    /**
     * @param int $productId
     * @return array
     */
    public function getUnusedPrices(int $productId): array
    {
        $ids = $this->em->getRepository(CommunicationPricePackage::class)->getIdsWithPrices($productId);

        return $this->em->getRepository(CommunicationPrice::class)->getPricesNoUsed($ids);
    }
}
