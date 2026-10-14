<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

interface BrandRepository
{
    public function findById(int $id): ?Brand;

    public function findBySlug(string $slug): ?Brand;

    /**
     * @param string|null $query texto libre sobre el nombre y el identificador
     * @return list<Brand> por nombre
     */
    public function search(?string $query, ?int $sellerId, ?bool $active): array;

    public function save(Brand $brand): void;
}
