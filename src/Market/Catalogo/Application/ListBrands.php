<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\SellerRepository;

final class ListBrands
{
    public function __construct(
        private readonly BrandRepository $brands,
        private readonly SellerRepository $sellers,
    ) {
    }

    /**
     * @return list<BrandDetails> por nombre
     */
    public function __invoke(?string $query, ?int $sellerId, ?bool $active): array
    {
        $sellers = [];

        return array_map(function ($brand) use (&$sellers): BrandDetails {
            $sellers[$brand->sellerId()] ??= $this->sellers->findById($brand->sellerId());

            return new BrandDetails($brand, $sellers[$brand->sellerId()]);
        }, $this->brands->search($query, $sellerId, $active));
    }
}
