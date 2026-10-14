<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerOrgUnits;
use App\Market\Catalogo\Domain\SellerRepository;

final class ListSellers
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly SellerOrgUnits $orgUnits,
    ) {
    }

    /**
     * @return list<SellerDetails> por nombre
     */
    public function __invoke(?string $query, ?bool $active): array
    {
        $sellers = $this->sellers->search($query, $active);
        $units = $this->orgUnits->findMany(array_values(array_unique(array_map(static fn (Seller $s) => $s->orgUnitId(), $sellers))));

        return array_map(static fn (Seller $s) => SellerDetails::of($s, $units[$s->orgUnitId()] ?? null), $sellers);
    }
}
