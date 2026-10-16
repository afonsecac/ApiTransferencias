<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Qué se guarda en una línea de stock. Los combos llegan con su historia; el modelo ya los admite. */
enum StockItemType: string
{
    case Product = 'product';
    case Combo = 'combo';
}
