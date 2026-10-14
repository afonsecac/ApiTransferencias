<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

interface SellerRepository
{
    public function findById(int $id): ?Seller;

    public function findBySlug(string $slug): ?Seller;

    public function findByOrgUnit(int $orgUnitId): ?Seller;

    public function findPlatform(): ?Seller;

    /**
     * @param string|null $query texto libre sobre el nombre y el identificador
     * @return list<Seller> por nombre
     */
    public function search(?string $query, ?bool $active): array;

    public function save(Seller $seller): void;
}
