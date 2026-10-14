<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Qué papel juega una unidad organizativa. Una misma unidad puede tener varios a la vez.
 */
enum OrgUnitType: string
{
    case PLATFORM = 'platform';
    case SELLER = 'seller';
    case WAREHOUSE_PARTNER = 'warehouse_partner';
    case CARRIER = 'carrier';
    case BUYER = 'buyer';
}
