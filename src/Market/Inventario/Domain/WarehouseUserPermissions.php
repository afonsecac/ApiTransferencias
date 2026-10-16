<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Lo que un usuario puede hacer en un almacén: stock, armado de combos, traslados y entregas. Un permiso solo vale en su almacén. */
final readonly class WarehouseUserPermissions
{
    public function __construct(
        public bool $stock = false,
        public bool $assembly = false,
        public bool $transfers = false,
        public bool $deliveries = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return !$this->stock && !$this->assembly && !$this->transfers && !$this->deliveries;
    }
}
