<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;

final class GetProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductDetailsAssembler $assembler,
    ) {
    }

    /**
     * @throws ProductNotFound
     */
    public function __invoke(int $id): ProductDetails
    {
        return $this->assembler->one($this->products->findById($id) ?? throw ProductNotFound::forId($id));
    }
}
