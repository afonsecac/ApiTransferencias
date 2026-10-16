<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserRepository;

/**
 * A qué almacenes llega un usuario y con qué permisos (COMR-288). Es la única fuente del aislamiento entre almacenes: el Voter y
 * los casos de uso del socio preguntan aquí, de modo que un permiso sobre el almacén A nunca vale en el B. Solo cuentan los
 * vínculos activos; un vínculo desactivado no da acceso a nada.
 */
final class WarehouseAccess
{
    private const PERMISSIONS = ['stock', 'assembly', 'transfers', 'deliveries'];

    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseUserRepository $links,
    ) {
    }

    /**
     * @return list<MyWarehouse> por orden de alta del vínculo
     */
    public function warehousesOf(int $userId): array
    {
        $mine = [];
        foreach ($this->links->ofUser($userId) as $link) {
            $warehouse = $link->isActive() ? $this->warehouses->findById($link->warehouseId()) : null;
            if ($warehouse instanceof Warehouse) {
                $mine[] = new MyWarehouse($warehouse, $link->permissions());
            }
        }

        return $mine;
    }

    /** El usuario tiene un vínculo activo con el almacén, aunque no tenga ningún permiso de operación en él. */
    public function canSee(int $userId, int $warehouseId): bool
    {
        return $this->activeLink($userId, $warehouseId) !== null;
    }

    /**
     * @param string $permission `stock`, `assembly`, `transfers` o `deliveries`; cualquier otro nunca se concede
     */
    public function allows(int $userId, int $warehouseId, string $permission): bool
    {
        if (!in_array($permission, self::PERMISSIONS, true)) {
            return false;
        }
        $link = $this->activeLink($userId, $warehouseId);

        return $link !== null && $link->permissions()->{$permission};
    }

    private function activeLink(int $userId, int $warehouseId): ?WarehouseUser
    {
        $link = $this->links->find($userId, $warehouseId);
        if ($link === null || !$link->isActive() || $this->warehouses->findById($warehouseId) === null) {
            return null;
        }

        return $link;
    }
}
