<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\StaffAlreadyActivated;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseStaff;
use App\Market\Inventario\Domain\WarehouseUserNotFound;
use App\Market\Inventario\Domain\WarehouseUserRepository;

final class ResendWarehouseUserInvitation
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
     * @throws StaffAlreadyActivated si ya activó su cuenta
     */
    public function __invoke(int $warehouseId, int $userId): void
    {
        $this->warehouses->findById($warehouseId) ?? throw WarehouseNotFound::forId($warehouseId);
        $this->links->find($userId, $warehouseId) ?? throw WarehouseUserNotFound::for($warehouseId, $userId);
        $this->staff->resendInvitation($userId);
    }
}
