<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

interface WarehouseRepository
{
    public function findById(int $id): ?Warehouse;

    public function findBySlug(string $slug): ?Warehouse;

    /** @return list<Warehouse> */
    public function findAll(): array;

    public function countByOrgUnit(int $orgUnitId): int;

    public function save(Warehouse $warehouse): void;

    public function remove(Warehouse $warehouse): void;
}
