<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerRepository;

final class GetSeller
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly SellerOrgUnits $orgUnits,
        private readonly ?ProductRepository $products = null,
        private readonly ?BrandRepository $brands = null,
    ) {
    }

    /**
     * @throws SellerNotFound
     */
    public function __invoke(int $id): SellerDetails
    {
        $seller = $this->sellers->findById($id) ?? throw SellerNotFound::forId($id);

        $productCounts = $this->products?->countsBySeller();

        return SellerDetails::of(
            $seller,
            $this->orgUnits->find($seller->orgUnitId()),
            $productCounts === null ? null : ($productCounts[$id] ?? 0),
            $this->brands === null ? null : count($this->brands->search(null, $id, null)),
        );
    }
}
