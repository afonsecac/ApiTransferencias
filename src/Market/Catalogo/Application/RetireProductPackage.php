<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductPackages;
use App\Market\Catalogo\Domain\ProductRepository;

/**
 * Deja de ofrecer el paquete de un producto (COMR-275). El paquete no se borra: si se vuelve a publicar, conserva su identidad.
 */
final class RetireProductPackage
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductPackages $packages,
    ) {
    }

    /**
     * @return bool false si el producto nunca se ofreció como paquete
     *
     * @throws ProductNotFound
     */
    public function __invoke(int $productId): bool
    {
        $this->products->findById($productId) ?? throw ProductNotFound::forId($productId);

        return $this->packages->retire($productId);
    }
}
