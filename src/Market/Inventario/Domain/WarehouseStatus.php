<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

enum WarehouseStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
}
