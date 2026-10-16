<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\StockMovement;
use App\Market\Inventario\Domain\StockProduct;
use App\Market\Inventario\Domain\Warehouse;

final readonly class StockMovementDetails
{
    public function __construct(public StockMovement $movement, public ?Warehouse $warehouse, public ?StockProduct $product)
    {
    }
}
