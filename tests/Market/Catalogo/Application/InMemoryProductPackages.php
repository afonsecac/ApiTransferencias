<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\PackageOffer;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductPackages;

final class InMemoryProductPackages implements ProductPackages
{
    /** @var array<int, PackageOffer> por id de producto */
    public array $offers = [];

    private int $nextPackageId = 100;

    public function publish(Product $product, Amount $price, string $currency): PackageOffer
    {
        $productId = (int) $product->id();
        $packageId = isset($this->offers[$productId]) ? $this->offers[$productId]->packageId : $this->nextPackageId++;

        return $this->offers[$productId] = new PackageOffer($packageId, $price, $currency, true);
    }

    public function retire(int $productId): bool
    {
        if (!isset($this->offers[$productId])) {
            return false;
        }
        $offer = $this->offers[$productId];
        $this->offers[$productId] = new PackageOffer($offer->packageId, $offer->price, $offer->currency, false);

        return true;
    }

    public function findFor(int $productId): ?PackageOffer
    {
        return $this->offers[$productId] ?? null;
    }
}
