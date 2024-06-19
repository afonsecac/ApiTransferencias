<?php

namespace App\Service;

use App\Entity\CommunicationClientPackage;
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
        return $this->em->getRepository(CommunicationClientPackage::class)->getAllPackages($product?->getEnvironment()?->getType(), $tenant);
    }
}
