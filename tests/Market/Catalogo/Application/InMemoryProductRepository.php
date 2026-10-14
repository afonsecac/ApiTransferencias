<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\ProductStatus;

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

    public function search(?string $query, ?ProductStatus $status, ?int $sellerId): array
    {
        $found = array_filter($this->products, static function (Product $p) use ($query, $status, $sellerId): bool {
            if ($status !== null && $p->status() !== $status) {
                return false;
            }
            if ($sellerId !== null && $p->sellerId() !== $sellerId) {
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

            return false;
        });
        usort($found, static fn (Product $a, Product $b) => strcmp($a->sku()->value(), $b->sku()->value()));

        return $found;
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
