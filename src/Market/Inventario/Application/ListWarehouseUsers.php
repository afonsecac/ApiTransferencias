<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseStaff;
use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserRepository;

final class ListWarehouseUsers
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseUserRepository $links,
        private readonly WarehouseStaff $staff,
    ) {
    }

    /**
     * @return list<WarehouseUserRow>
     *
     * @throws WarehouseNotFound
     */
    public function __invoke(int $warehouseId): array
    {
        $this->warehouses->findById($warehouseId) ?? throw WarehouseNotFound::forId($warehouseId);
        $links = $this->links->ofWarehouse($warehouseId);
        $members = $this->staff->findMany(array_map(static fn (WarehouseUser $l) => $l->userId(), $links));

        return array_map(static fn (WarehouseUser $l) => new WarehouseUserRow($l, $members[$l->userId()] ?? null), $links);
    }
}
