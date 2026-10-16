<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseCostCenter;
use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseMunicipality;
use App\Market\Inventario\Domain\WarehouseOwner;
use App\Market\Inventario\Domain\WarehouseOwners;

/**
 * Un almacén con su dueño, su centro de costo y su municipio resueltos, el régimen deducido del dueño y lo que falta para activarlo.
 */
final class WarehouseDetails
{
    public readonly ?string $regime;
    /** @var list<string> */
    public readonly array $missing;

    public function __construct(
        public readonly Warehouse $warehouse,
        public readonly ?WarehouseOwner $owner,
        public readonly ?WarehouseMunicipality $municipality,
    ) {
        $this->regime = $owner?->regime();
        $this->missing = $warehouse->missingForActivation();
    }

    public static function of(Warehouse $warehouse, WarehouseOwners $owners, WarehouseMunicipalities $municipalities): self
    {
        return new self(
            $warehouse,
            $warehouse->orgUnitId() === null ? null : $owners->find($warehouse->orgUnitId()),
            $warehouse->municipalityId() === null ? null : $municipalities->find($warehouse->municipalityId()),
        );
    }

    public function costCenter(): ?WarehouseCostCenter
    {
        foreach ($this->owner->costCenters ?? [] as $center) {
            if ($center->id === $this->warehouse->costCenterId()) {
                return $center;
            }
        }

        return null;
    }
}
