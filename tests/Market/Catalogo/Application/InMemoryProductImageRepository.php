<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageRepository;

final class InMemoryProductImageRepository implements ProductImageRepository
{
    /** @var array<int, ProductImage> */
    public array $images = [];

    public bool $failOnSave = false;

    private int $nextId = 1;

    public function findByProduct(int $productId): array
    {
        $found = array_values(array_filter($this->images, static fn (ProductImage $i): bool => $i->productId() === $productId));
        usort($found, static fn (ProductImage $a, ProductImage $b): int => $a->position() <=> $b->position());

        return $found;
    }

    public function save(ProductImage $image): void
    {
        $this->saveAll([$image]);
    }

    public function saveAll(array $images): void
    {
        if ($this->failOnSave) {
            throw new \RuntimeException('Fallo de base de datos');
        }
        foreach ($images as $image) {
            if ($image->id() === null) {
                (new \ReflectionProperty(ProductImage::class, 'id'))->setValue($image, $this->nextId++);
            }
            $this->images[$image->id()] = $image;
        }
    }

    public function delete(ProductImage $image): void
    {
        unset($this->images[$image->id()]);
    }
}
