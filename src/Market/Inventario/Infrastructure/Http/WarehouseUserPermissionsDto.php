<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\DTO\IInput;

/**
 * Body de PUT /dashboard/api/market/warehouses/{id}/users/{userId}/permissions: debe quedar al menos un permiso.
 */
class WarehouseUserPermissionsDto implements IInput
{
    protected ?bool $stock;

    protected ?bool $assembly;

    protected ?bool $transfers;

    protected ?bool $deliveries;

    public function __construct(?bool $stock = null, ?bool $assembly = null, ?bool $transfers = null, ?bool $deliveries = null)
    {
        $this->stock = $stock;
        $this->assembly = $assembly;
        $this->transfers = $transfers;
        $this->deliveries = $deliveries;
    }

    public function getStock(): ?bool
    {
        return $this->stock;
    }

    public function setStock(?bool $stock): void
    {
        $this->stock = $stock;
    }

    public function getAssembly(): ?bool
    {
        return $this->assembly;
    }

    public function setAssembly(?bool $assembly): void
    {
        $this->assembly = $assembly;
    }

    public function getTransfers(): ?bool
    {
        return $this->transfers;
    }

    public function setTransfers(?bool $transfers): void
    {
        $this->transfers = $transfers;
    }

    public function getDeliveries(): ?bool
    {
        return $this->deliveries;
    }

    public function setDeliveries(?bool $deliveries): void
    {
        $this->deliveries = $deliveries;
    }
}
