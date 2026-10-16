<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

interface WarehouseUserRepository
{
    public function find(int $userId, int $warehouseId): ?WarehouseUser;

    /** @return list<WarehouseUser> por orden de alta */
    public function ofWarehouse(int $warehouseId): array;

    /** @return list<WarehouseUser> los vínculos del usuario, activos o no, por orden de alta */
    public function ofUser(int $userId): array;

    public function save(WarehouseUser $link): void;
}
