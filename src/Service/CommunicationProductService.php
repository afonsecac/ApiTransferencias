<?php

namespace App\Service;

use App\Entity\CommunicationProduct;
use App\Repository\CommunicationProductRepository;
use App\Service\CommonService;
use App\Util\IPaginationResponse;

class CommunicationProductService extends CommonService
{
    /**
     * @param int $page
     * @param int $limit
     * @param string $env
     * @param string|null $query
     * @return \App\Util\IPaginationResponse
     */
    public function getProducts(int $page = 0, int $limit = 10, string $env = 'TEST', string $query = null): IPaginationResponse
    {
        /** @var \App\Repository\CommunicationProductRepository $productRepo */
        $productRepo = $this->em->getRepository(CommunicationProduct::class);
        return $productRepo->getProducts($page, $limit, $env, $query);
    }
}
