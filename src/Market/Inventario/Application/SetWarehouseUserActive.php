<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseStaff;
use App\Market\Inventario\Domain\WarehouseUserNotFound;
use App\Market\Inventario\Domain\WarehouseUserRepository;

final class SetWarehouseUserActive
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseUserRepository $links,
        private readonly WarehouseStaff $staff,
    ) {
    }

    /**
     * Activar o desactivar el vínculo; no se borra.
     *
     * @throws WarehouseNotFound
     * @throws WarehouseUserNotFound
     */
    public function __invoke(int $warehouseId, int $userId, bool $active): WarehouseUserRow
    {
        $this->warehouses->findById($warehouseId) ?? throw WarehouseNotFound::forId($warehouseId);
        $link = $this->links->find($userId, $warehouseId) ?? throw WarehouseUserNotFound::for($warehouseId, $userId);
        $active ? $link->activate() : $link->deactivate();
        $this->links->save($link);

        return new WarehouseUserRow($link, $this->staff->findMany([$userId])[$userId] ?? null);
    }
}
