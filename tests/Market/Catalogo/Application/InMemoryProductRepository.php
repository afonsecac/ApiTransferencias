<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductFilter;
use App\Market\Catalogo\Domain\ProductRepository;

final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<int, Product> */
    public array $products = [];

    public int $saves = 0;

    private int $nextId = 1;

    public function findById(int $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    public function findBySku(string $sku): ?Product
    {
        foreach ($this->products as $product) {
            if ($product->sku()->value() === $sku) {
                return $product;
            }
        }

        return null;
    }

    public function findByIds(array $ids): array
    {
        return array_values(array_filter($this->products, static fn (Product $p): bool => in_array($p->id(), $ids, true)));
    }

    public function productIdsByCategory(): array
    {
        $byCategory = [];
        foreach ($this->products as $product) {
            foreach ($product->categoryIds() as $categoryId) {
                $byCategory[$categoryId][] = (int) $product->id();
            }
        }

        return $byCategory;
    }

    public function hasPhysicalProducts(): bool
    {
        foreach ($this->products as $product) {
            if ($product->spec()->content !== null) {
                return true;
            }
        }

        return false;
    }

    public function countsByBrand(): array
    {
        $counts = [];
        foreach ($this->products as $product) {
            if ($product->brandId() !== null) {
                $counts[$product->brandId()] = ($counts[$product->brandId()] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function countsBySeller(): array
    {
        $counts = [];
        foreach ($this->products as $product) {
            $counts[$product->sellerId()] = ($counts[$product->sellerId()] ?? 0) + 1;
        }

        return $counts;
    }

    public function skusStartingWith(string $prefix): array
    {
        $prefix = strtoupper($prefix);

        return array_values(array_filter(
            array_map(static fn (Product $p): string => $p->sku()->value(), $this->products),
            static fn (string $sku): bool => str_starts_with($sku, $prefix),
        ));
    }

    public function search(ProductFilter $filter, ?int $limit = null, int $offset = 0): array
    {
        $found = $this->matching($filter);

        return $limit === null ? $found : array_slice($found, $offset, $limit);
    }

    public function count(ProductFilter $filter): int
    {
        return count($this->matching($filter));
    }

    /**
     * @return list<Product> por sku
     */
    private function matching(ProductFilter $filter): array
    {
        $query = $filter->query;
        $found = array_filter($this->products, static function (Product $p) use ($filter, $query): bool {
            if ($filter->status !== null && $p->status() !== $filter->status) {
                return false;
            }
            if ($filter->sellerId !== null && $p->sellerId() !== $filter->sellerId) {
                return false;
            }
            if ($filter->brandId !== null && $p->brandId() !== $filter->brandId) {
                return false;
            }
            if ($filter->categoryIds !== [] && array_intersect($filter->categoryIds, $p->categoryIds()) === []) {
                return false;
            }
            if ($filter->relevantOn !== null && !$p->isRelevantOn($filter->relevantOn)) {
                return false;
            }
            if ($query === null || $query === '') {
                return true;
            }
            if (stripos($p->sku()->value(), $query) !== false) {
                return true;
            }
            foreach ($p->spec()->texts->all() as $text) {
                if (stripos($text['name'], $query) !== false) {
                    return true;
                }
            }

            return $p->spec()->barcode !== null && stripos($p->spec()->barcode, $query) !== false;
        });
        usort($found, static fn (Product $a, Product $b) => strcmp($a->sku()->value(), $b->sku()->value()));

        return array_values($found);
    }

    public function save(Product $product): void
    {
        if ($product->id() === null) {
            (new \ReflectionProperty(Product::class, 'id'))->setValue($product, $this->nextId++);
        }
        $this->products[$product->id()] = $product;
        ++$this->saves;
    }
}
