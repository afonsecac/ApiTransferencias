<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandNotFound;
use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\InvalidBrand;
use App\Market\Catalogo\Domain\SellerRepository;

final class UpdateBrand
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * Solo cambia el nombre: el identificador y el vendedor no cambian.
     *
     * @throws BrandNotFound
     * @throws InvalidBrand
     */
    public function __invoke(int $id, string $name): BrandDetails
    {
        $brand = $this->brands->findById($id) ?? throw BrandNotFound::forId($id);
        $brand->rename($name);
        $this->brands->save($brand);

        return new BrandDetails($brand, $this->sellers->findById($brand->sellerId()));
    }
}
