<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerRepository;

/**
 * La consulta que Catálogo ofrece a Inventario para el stock: el producto con su vendedor (propietario) y su centro de costo.
 */
final class GetProductStockFacts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
    ) {
    }

    public function __invoke(int $id): ?ProductStockFacts
    {
        $product = $this->products->findById($id);

        return $product === null ? null : $this->facts($product);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, ProductStockFacts> por id; faltan los que no existen
     */
    public function many(array $ids): array
    {
        $facts = [];
        foreach ($this->products->findByIds($ids) as $product) {
            $facts[(int) $product->id()] = $this->facts($product);
        }

        return $facts;
    }

    private function facts(Product $product): ProductStockFacts
    {
        $seller = $this->sellers->findById($product->sellerId());
        $spec = $product->spec();

        return new ProductStockFacts(
            (int) $product->id(),
            $product->sku()->value(),
            $spec->texts->forLanguage('es')['es']['name'] ?? $product->sku()->value(),
            $product->sellerId(),
            $seller?->name() ?? '',
            $seller?->costCenterId() ?? 0,
            !$spec->isDigital() && $spec->deliveryBasis !== null,
        );
    }
}
