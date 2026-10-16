<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\InvalidWarehouseUser;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseStaff;
use App\Market\Inventario\Domain\WarehouseUserNotFound;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use App\Market\Inventario\Domain\WarehouseUserRepository;

final class ChangeWarehouseUserPermissions
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseUserRepository $links,
        private readonly WarehouseStaff $staff,
    ) {
    }

    /**
     * @throws WarehouseNotFound
     * @throws WarehouseUserNotFound
     * @throws InvalidWarehouseUser si no queda ningún permiso
     */
    public function __invoke(int $warehouseId, int $userId, WarehouseUserPermissions $permissions): WarehouseUserRow
    {
        $this->warehouses->findById($warehouseId) ?? throw WarehouseNotFound::forId($warehouseId);
        $link = $this->links->find($userId, $warehouseId) ?? throw WarehouseUserNotFound::for($warehouseId, $userId);
        $link->changePermissions($permissions);
        $this->links->save($link);

        return new WarehouseUserRow($link, $this->staff->findMany([$userId])[$userId] ?? null);
    }
}
