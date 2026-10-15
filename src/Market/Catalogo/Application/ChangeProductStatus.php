<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\InvalidProductTransition;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductPackages;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductStatus;

final class ChangeProductStatus
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductDetailsAssembler $assembler,
        private readonly ProductPackages $packages,
    ) {
    }

    /**
     * Publicar (desde borrador o retirado) o retirar (solo un publicado). Nunca se vuelve a borrador. Retirar un producto retira
     * también su oferta al comprador (COMR-275); publicarlo de nuevo no la reactiva: se ofrece otra vez a propósito, con su precio.
     *
     * @throws ProductNotFound
     * @throws InvalidProductTransition
     * @throws InvalidProduct publicar sin ninguna categoría
     */
    public function __invoke(int $id, ProductStatus $status): ProductDetails
    {
        $product = $this->products->findById($id) ?? throw ProductNotFound::forId($id);
        match ($status) {
            ProductStatus::PUBLISHED => $product->publish(),
            ProductStatus::WITHDRAWN => $product->withdraw(),
            ProductStatus::DRAFT => throw InvalidProductTransition::from($product->status(), $status),
        };
        $this->products->save($product);
        if ($status === ProductStatus::WITHDRAWN) {
            $this->packages->retire($id);
        }

        return $this->assembler->one($product);
    }
}
