<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerRepository;

/**
 * La consulta que Catálogo ofrece a las gift cards: el valor de un producto digital y la unidad organizativa de su vendedor.
 */
final class GetProductGiftCardFacts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SellerRepository $sellers,
    ) {
    }

    public function __invoke(int $id): ?ProductGiftCardFacts
    {
        $product = $this->products->findById($id);

        return $product === null ? null : $this->facts($product);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, ProductGiftCardFacts> por id
     */
    public function many(array $ids): array
    {
        $facts = [];
        foreach ($this->products->findByIds($ids) as $product) {
            $facts[(int) $product->id()] = $this->facts($product);
        }

        return $facts;
    }

    private function facts(Product $product): ProductGiftCardFacts
    {
        $spec = $product->spec();
        $value = $spec->value;
        $name = $spec->texts->forLanguage('es')['es']['name'] ?? $product->sku()->value();
        $orgUnitId = $this->sellers->findById($product->sellerId())?->orgUnitId() ?? 0;

        if ($value === null) {
            return new ProductGiftCardFacts((int) $product->id(), $name, false, false, '', 0, null, $orgUnitId);
        }

        return new ProductGiftCardFacts(
            (int) $product->id(),
            $name,
            true,
            $value->isFixed(),
            $value->currency(),
            $value->minAmount()->minorUnits(),
            $value->maxAmount()?->minorUnits(),
            $orgUnitId,
        );
    }
}
