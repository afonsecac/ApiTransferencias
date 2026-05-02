<?php

namespace App\Service;

use App\DTO\UpdateProductDto;
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
    public function getProducts(int $page = 0, int $limit = 10, string $env = 'TEST', ?string $query = null): IPaginationResponse
    {
        /** @var \App\Repository\CommunicationProductRepository $productRepo */
        $productRepo = $this->em->getRepository(CommunicationProduct::class);
        return $productRepo->getProducts($page, $limit, $env, $query);
    }

    public function updateProduct(CommunicationProduct $product, UpdateProductDto $dto): CommunicationProduct
    {
        if ($dto->getDescription() !== null) {
            $product->setDescription(mb_substr($dto->getDescription(), 0, 255));
        }
        if ($dto->getPackageType() !== null) {
            $product->setPackageType($dto->getPackageType());
        }
        if ($dto->getProductType() !== null) {
            $product->setProductType($dto->getProductType());
        }
        if ($dto->getPrice() !== null) {
            $product->setPrice($dto->getPrice());
        }
        if ($dto->getInitialDate() !== null) {
            $product->setInitialDate(new \DateTimeImmutable($dto->getInitialDate()));
        }
        if ($dto->getEndDateAt() !== null) {
            $product->setEndDateAt(new \DateTimeImmutable($dto->getEndDateAt()));
        }

        $this->em->flush();

        return $product;
    }
}
