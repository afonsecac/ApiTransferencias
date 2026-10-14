<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandNotFound;
use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\SellerRepository;

final class SetBrandActive
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * Desactivarla no la quita de los productos que la usan: solo la oculta de los selectores.
     *
     * @throws BrandNotFound
     */
    public function __invoke(int $id, bool $active): BrandDetails
    {
        $brand = $this->brands->findById($id) ?? throw BrandNotFound::forId($id);
        $active ? $brand->activate() : $brand->deactivate();
        $this->brands->save($brand);

        return new BrandDetails($brand, $this->sellers->findById($brand->sellerId()));
    }
}
