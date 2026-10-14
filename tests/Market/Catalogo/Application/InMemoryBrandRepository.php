<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\BrandRepository;

final class InMemoryBrandRepository implements BrandRepository
{
    /** @var array<int, Brand> */
    public array $brands = [];

    public int $saves = 0;

    private int $nextId = 1;

    public function findById(int $id): ?Brand
    {
        return $this->brands[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Brand
    {
        foreach ($this->brands as $brand) {
            if ($brand->slug()->value() === $slug) {
                return $brand;
            }
        }

        return null;
    }

    public function search(?string $query, ?int $sellerId, ?bool $active): array
    {
        $found = array_filter($this->brands, static function (Brand $b) use ($query, $sellerId, $active): bool {
            if ($sellerId !== null && $b->sellerId() !== $sellerId) {
                return false;
            }
            if ($active !== null && $b->isActive() !== $active) {
                return false;
            }

            return $query === null || $query === '' || stripos($b->name(), $query) !== false || stripos($b->slug()->value(), $query) !== false;
        });
        usort($found, static fn (Brand $a, Brand $b) => strcmp($a->name(), $b->name()));

        return $found;
    }

    public function save(Brand $brand): void
    {
        if ($brand->id() === null) {
            (new \ReflectionProperty(Brand::class, 'id'))->setValue($brand, $this->nextId++);
        }
        $this->brands[$brand->id()] = $brand;
        ++$this->saves;
    }
}
