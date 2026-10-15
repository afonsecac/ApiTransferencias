<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerRepository;

final class ListBrands
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly SellerRepository $sellers,
        private readonly ?ProductRepository $products = null,
    ) {
    }

    /**
     * @return list<BrandDetails> por nombre
     */
    public function __invoke(?string $query, ?int $sellerId, ?bool $active): array
    {
        $sellers = [];
        $counts = $this->products?->countsByBrand();

        return array_map(function ($brand) use (&$sellers, $counts): BrandDetails {
            $sellers[$brand->sellerId()] ??= $this->sellers->findById($brand->sellerId());

            return new BrandDetails($brand, $sellers[$brand->sellerId()], $counts === null ? null : ($counts[(int) $brand->id()] ?? 0));
        }, $this->brands->search($query, $sellerId, $active));
    }
}
