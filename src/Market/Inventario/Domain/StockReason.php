<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** El motivo, obligatorio, de un ajuste de stock. */
enum StockReason: string
{
    case Initial = 'initial';
    case Count = 'count';
    case Damage = 'damage';
    case Loss = 'loss';
    case Return = 'return';
    case Other = 'other';
}
