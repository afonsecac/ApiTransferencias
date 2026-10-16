<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\PartnerPermissions;

final readonly class WarehouseInput
{
    public function __construct(
        public string $name,
        public ?string $address,
        public ?int $municipalityId,
        public ?int $orgUnitId,
        public ?int $costCenterId,
        public PartnerPermissions $permissions,
    ) {
    }
}
