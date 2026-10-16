<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Vínculo de un usuario del dashboard con un almacén (COMR-287), con sus permisos en él. Una persona puede estar en varios
 * almacenes con permisos distintos; el par (usuario, almacén) es único. No se borra: se desactiva. No es una cuenta aparte: la
 * cuenta (activación, 2FA) es la del usuario del dashboard.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_warehouse_user')]
#[ORM\UniqueConstraint(name: 'uniq_market_warehouse_user', columns: ['user_id', 'warehouse_id'])]
#[ORM\Index(name: 'idx_market_warehouse_user_warehouse', columns: ['warehouse_id'])]
class WarehouseUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', type: 'integer')]
    private int $userId;

    #[ORM\Column(name: 'warehouse_id', type: 'integer')]
    private int $warehouseId;

    #[ORM\Column(name: 'perm_stock', type: 'boolean')]
    private bool $stock;

    #[ORM\Column(name: 'perm_assembly', type: 'boolean')]
    private bool $assembly;

    #[ORM\Column(name: 'perm_transfers', type: 'boolean')]
    private bool $transfers;

    #[ORM\Column(name: 'perm_deliveries', type: 'boolean')]
    private bool $deliveries;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(int $userId, int $warehouseId, WarehouseUserPermissions $permissions)
    {
        if ($userId <= 0 || $warehouseId <= 0) {
            throw new InvalidWarehouseUser('Un vínculo necesita usuario y almacén.');
        }
        $this->userId = $userId;
        $this->warehouseId = $warehouseId;
        $this->applyPermissions($permissions);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function link(int $userId, int $warehouseId, WarehouseUserPermissions $permissions): self
    {
        return new self($userId, $warehouseId, $permissions);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function warehouseId(): int
    {
        return $this->warehouseId;
    }

    public function permissions(): WarehouseUserPermissions
    {
        return new WarehouseUserPermissions($this->stock, $this->assembly, $this->transfers, $this->deliveries);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changePermissions(WarehouseUserPermissions $permissions): void
    {
        $this->applyPermissions($permissions);
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->touch();
    }

    public function activate(): void
    {
        $this->active = true;
        $this->touch();
    }

    private function applyPermissions(WarehouseUserPermissions $permissions): void
    {
        if ($permissions->isEmpty()) {
            throw new InvalidWarehouseUser('El usuario necesita al menos un permiso en el almacén.');
        }
        $this->stock = $permissions->stock;
        $this->assembly = $permissions->assembly;
        $this->transfers = $permissions->transfers;
        $this->deliveries = $permissions->deliveries;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
