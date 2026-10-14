<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandNotFound;
use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\SellerRepository;

final class GetBrand
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * @throws BrandNotFound
     */
    public function __invoke(int $id): BrandDetails
    {
        $brand = $this->brands->findById($id) ?? throw BrandNotFound::forId($id);

        return new BrandDetails($brand, $this->sellers->findById($brand->sellerId()));
    }
}
