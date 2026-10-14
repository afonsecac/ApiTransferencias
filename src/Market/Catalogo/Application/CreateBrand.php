<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\BrandAlreadyExists;
use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\BrandSellerInvalid;
use App\Market\Catalogo\Domain\InvalidBrand;
use App\Market\Catalogo\Domain\InvalidSeller;
use App\Market\Catalogo\Domain\SellerRepository;
use App\Market\Catalogo\Domain\Slug;

final class CreateBrand
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * @param string|null $slug por defecto, derivado del nombre
     * @throws InvalidBrand
     * @throws InvalidSeller si el identificador no es válido
     * @throws BrandSellerInvalid
     * @throws BrandAlreadyExists
     */
    public function __invoke(int $sellerId, string $name, ?string $slug = null): BrandDetails
    {
        $slugValue = $slug !== null && trim($slug) !== '' ? new Slug(trim($slug)) : Slug::fromText($name);

        $seller = $this->sellers->findById($sellerId) ?? throw BrandSellerInvalid::unknown($sellerId);
        if (!$seller->isActive()) {
            throw BrandSellerInvalid::inactive($seller->name());
        }
        if ($this->brands->findBySlug($slugValue->value()) !== null) {
            throw BrandAlreadyExists::forSlug($slugValue->value());
        }

        $brand = Brand::create($sellerId, $slugValue, $name);
        $this->brands->save($brand);

        return new BrandDetails($brand, $seller);
    }
}
