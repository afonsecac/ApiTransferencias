<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerRepository;

final class ListSellers
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly SellerOrgUnits $orgUnits,
        private readonly ?ProductRepository $products = null,
        private readonly ?BrandRepository $brands = null,
    ) {
    }

    /**
     * @return list<SellerDetails> por nombre
     */
    public function __invoke(?string $query, ?bool $active): array
    {
        $sellers = $this->sellers->search($query, $active);
        $units = $this->orgUnits->findMany(array_values(array_unique(array_map(static fn (Seller $s) => $s->orgUnitId(), $sellers))));

        $productCounts = $this->products?->countsBySeller();
        $brandCounts = $this->brandCounts();

        return array_map(
            static fn (Seller $s) => SellerDetails::of(
                $s,
                $units[$s->orgUnitId()] ?? null,
                $productCounts === null ? null : ($productCounts[(int) $s->id()] ?? 0),
                $brandCounts === null ? null : ($brandCounts[(int) $s->id()] ?? 0),
            ),
            $sellers,
        );
    }

    /**
     * @return array<int, int>|null vendedor → cuántas marcas tiene (de cualquier estado); null si no se cuentan
     */
    private function brandCounts(): ?array
    {
        if ($this->brands === null) {
            return null;
        }
        $counts = [];
        foreach ($this->brands->search(null, null, null) as $brand) {
            $counts[$brand->sellerId()] = ($counts[$brand->sellerId()] ?? 0) + 1;
        }

        return $counts;
    }
}
