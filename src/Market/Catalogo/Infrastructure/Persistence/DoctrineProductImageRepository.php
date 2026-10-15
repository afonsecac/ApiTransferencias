<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProductImageRepository implements ProductImageRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByProduct(int $productId): array
    {
        /** @var list<ProductImage> $images */
        $images = $this->em->getRepository(ProductImage::class)->findBy(['productId' => $productId], ['position' => 'ASC']);

        return $images;
    }

    public function findByProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $byProduct = [];
        foreach ($this->em->getRepository(ProductImage::class)->findBy(['productId' => $productIds], ['position' => 'ASC']) as $image) {
            $byProduct[$image->productId()][] = $image;
        }

        return $byProduct;
    }

    public function save(ProductImage $image): void
    {
        $this->saveAll([$image]);
    }

    public function saveAll(array $images): void
    {
        // Una sola transacción: reordenar cambia las posiciones de varias filas y la unicidad se comprueba al confirmar.
        $this->em->wrapInTransaction(function () use ($images): void {
            foreach ($images as $image) {
                $this->em->persist($image);
            }
            $this->em->flush();
        });
    }

    public function delete(ProductImage $image): void
    {
        $this->em->remove($image);
        $this->em->flush();
    }
}
