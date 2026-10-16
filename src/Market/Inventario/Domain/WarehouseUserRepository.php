<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

interface WarehouseUserRepository
{
    public function find(int $userId, int $warehouseId): ?WarehouseUser;

    /** @return list<WarehouseUser> por orden de alta */
    public function ofWarehouse(int $warehouseId): array;

    public function save(WarehouseUser $link): void;
}
