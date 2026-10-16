<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\StockLine;
use App\Market\Inventario\Domain\StockProduct;
use App\Market\Inventario\Domain\Warehouse;

/** Una línea de existencias con su almacén y producto resueltos, lo disponible y si está bajo mínimos. */
final readonly class StockLineDetails
{
    public bool $warehouseActive;
    public int $available;
    public bool $low;

    public function __construct(public StockLine $line, public ?Warehouse $warehouse, public ?StockProduct $product)
    {
        $this->warehouseActive = $warehouse?->isActive() ?? false;
        $this->available = $line->available($this->warehouseActive);
        $this->low = $line->isLow();
    }
}
