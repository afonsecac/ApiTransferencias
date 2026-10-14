<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * La galería de imágenes de un producto (COMR-274): una lista ordenada con exactamente una imagen de referencia mientras no
 * esté vacía. La primera imagen que se añade es la referencia; al quitar la referencia pasa a serlo la primera de las que
 * quedan; las posiciones son siempre 1..n sin huecos. Con un máximo de imágenes configurable.
 */
final class ProductGallery
{
    /** @var list<ProductImage> */
    private array $images;

    /**
     * @param list<ProductImage> $images las ya guardadas, en cualquier orden (se ordenan por posición)
     */
    private function __construct(array $images, private readonly int $max)
    {
        usort($images, static fn (ProductImage $a, ProductImage $b): int => $a->position() <=> $b->position());
        $this->images = $images;
    }

    /**
     * @param list<ProductImage> $images
     */
    public static function of(array $images, int $max): self
    {
        return new self($images, $max);
    }

    /**
     * @return list<ProductImage> en el orden de la galería
     */
    public function images(): array
    {
        return $this->images;
    }

    public function count(): int
    {
        return count($this->images);
    }

    public function reference(): ?ProductImage
    {
        foreach ($this->images as $image) {
            if ($image->isReference()) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Añade al final; la primera de una galería vacía es la referencia.
     *
     * @throws ProductGalleryFull
     */
    public function add(ProductImage $image): void
    {
        if (count($this->images) >= $this->max) {
            throw ProductGalleryFull::withMax($this->max);
        }
        $image->moveTo(count($this->images) + 1);
        if ($this->images === []) {
            $image->markAsReference();
        } else {
            $image->unmarkAsReference();
        }
        $this->images[] = $image;
    }

    /**
     * @param list<int> $ids el orden nuevo: todas las imágenes de la galería, una vez cada una
     *
     * @throws InvalidGalleryOrder
     */
    public function reorder(array $ids): void
    {
        $current = array_map(static fn (ProductImage $i): int => (int) $i->id(), $this->images);
        $sortedWanted = $ids;
        $sortedCurrent = $current;
        sort($sortedWanted);
        sort($sortedCurrent);
        if ($sortedWanted !== $sortedCurrent) {
            throw InvalidGalleryOrder::notAPermutation();
        }
        $byId = [];
        foreach ($this->images as $image) {
            $byId[(int) $image->id()] = $image;
        }
        $this->images = array_map(static fn (int $id): ProductImage => $byId[$id], $ids);
        $this->renumber();
    }

    /**
     * @throws ProductImageNotFound
     */
    public function setReference(int $id): void
    {
        $target = $this->find($id);
        foreach ($this->images as $image) {
            $image->unmarkAsReference();
        }
        $target->markAsReference();
    }

    /**
     * Quita una imagen: las posiciones se compactan y, si era la referencia, lo pasa a ser la primera de las que quedan.
     *
     * @throws ProductImageNotFound
     */
    public function remove(int $id): ProductImage
    {
        $removed = $this->find($id);
        $this->images = array_values(array_filter($this->images, static fn (ProductImage $i): bool => $i !== $removed));
        $this->renumber();
        if ($removed->isReference() && $this->images !== []) {
            $this->images[0]->markAsReference();
        }
        $removed->unmarkAsReference();

        return $removed;
    }

    /**
     * @throws ProductImageNotFound
     */
    public function find(int $id): ProductImage
    {
        foreach ($this->images as $image) {
            if ($image->id() === $id) {
                return $image;
            }
        }

        throw ProductImageNotFound::forId($id);
    }

    private function renumber(): void
    {
        foreach ($this->images as $index => $image) {
            $image->moveTo($index + 1);
        }
    }
}
