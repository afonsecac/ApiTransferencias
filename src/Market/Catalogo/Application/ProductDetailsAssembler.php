<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\SellerRepository;

/**
 * Une un producto con su vendedor, su marca y sus categorías. Al armar varios a la vez reutiliza lo que ya leyó.
 */
final class ProductDetailsAssembler
{
    public function __construct(
        private readonly SellerRepository $sellers,
        private readonly BrandRepository $brands,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function one(Product $product): ProductDetails
    {
        return $this->many([$product])[0];
    }

    /**
     * @param list<Product> $products
     *
     * @return list<ProductDetails>
     */
    public function many(array $products): array
    {
        $sellers = [];
        $brands = [];
        /** @var array<int, Category>|null $categories */
        $categories = null;
        $details = [];
        foreach ($products as $product) {
            $sellers[$product->sellerId()] ??= $this->sellers->findById($product->sellerId());
            $brandId = $product->brandId();
            if ($brandId !== null) {
                $brands[$brandId] ??= $this->brands->findById($brandId);
            }
            $ids = $product->categoryIds();
            if ($ids !== [] && $categories === null) {
                $categories = [];
                foreach ($this->categories->all() as $category) {
                    $categories[(int) $category->id()] = $category;
                }
            }
            $details[] = new ProductDetails(
                $product,
                $sellers[$product->sellerId()],
                $brandId === null ? null : $brands[$brandId],
                array_values(array_filter(array_map(static fn (int $id): ?Category => $categories[$id] ?? null, $ids))),
                $ids === [] ? [] : array_combine($ids, array_map(static fn (int $id): string => self::path($categories ?? [], $id), $ids)),
            );
        }

        return $details;
    }

    /**
     * @param array<int, Category> $categories
     */
    private static function path(array $categories, int $id): string
    {
        $names = [];
        for ($current = $categories[$id] ?? null, $guard = 0; $current !== null && $guard < 10; ++$guard) {
            array_unshift($names, $current->nameEs());
            $current = $current->parentId() === null ? null : ($categories[$current->parentId()] ?? null);
        }

        return implode(' › ', $names);
    }
}
