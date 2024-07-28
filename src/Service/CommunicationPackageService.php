<?php

namespace App\Service;

use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Service\CommonService;

class CommunicationPackageService extends CommonService
{
    /**
     * @param int $productId
     * @param int|null $tenant
     * @return CommunicationClientPackage[]
     */
    public function all(int $productId, int $tenant = null): array
    {
        $product = $this->em->getRepository(CommunicationProduct::class)->find($productId);
        $productList = $this->em->getRepository(CommunicationClientPackage::class)->getAllPackages($product?->getEnvironment()?->getType(), $tenant);
        if (count($productList) === 0) {
            $productList = $this->em->getRepository(CommunicationPricePackage::class)->findBy([], [
                'amount' => 'ASC'
            ]);
        }
        return $productList;
    }
}
