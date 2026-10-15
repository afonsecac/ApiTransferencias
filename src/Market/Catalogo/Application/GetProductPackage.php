<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\PackageOffer;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductPackages;
use App\Market\Catalogo\Domain\ProductRepository;

/**
 * El paquete con el que se ofrece un producto al comprador, o null si no se ha ofrecido (COMR-275).
 */
final class GetProductPackage
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductPackages $packages,
    ) {
    }

    /**
     * @throws ProductNotFound
     */
    public function __invoke(int $productId): ?PackageOffer
    {
        $this->products->findById($productId) ?? throw ProductNotFound::forId($productId);

        return $this->packages->findFor($productId);
    }
}
