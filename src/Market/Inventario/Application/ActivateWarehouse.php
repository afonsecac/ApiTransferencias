<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseIncomplete;
use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseOwners;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseTransitionInvalid;

final class ActivateWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseOwners $owners,
        private readonly WarehouseMunicipalities $municipalities,
    ) {
    }

    /**
     * De borrador o de inactivo. Pide el almacén completo y vuelve a validar el dueño, su centro de costo y el municipio: reactivar
     * repite las mismas validaciones que activar.
     *
     * @throws WarehouseNotFound
     * @throws WarehouseIncomplete con el detalle de lo que falta
     * @throws WarehouseTransitionInvalid
     */
    public function __invoke(int $id): WarehouseDetails
    {
        $warehouse = $this->warehouses->findById($id) ?? throw WarehouseNotFound::forId($id);
        $details = WarehouseDetails::of($warehouse, $this->owners, $this->municipalities);

        $missing = array_flip($warehouse->missingForActivation());
        if ($warehouse->orgUnitId() !== null) {
            if ($details->owner === null || $details->owner->regime() === null) {
                $missing['owner'] = true;
            } elseif ($warehouse->costCenterId() !== null && $details->owner->activeCostCenter($warehouse->costCenterId()) === null) {
                $missing['cost_center'] = true;
            }
        }
        if ($warehouse->municipalityId() !== null && ($details->municipality === null || !$details->municipality->selectable)) {
            $missing['municipality'] = true;
        }
        $ordered = array_values(array_filter(['owner', 'cost_center', 'municipality'], static fn (string $k) => isset($missing[$k])));
        if ($ordered !== []) {
            throw new WarehouseIncomplete($ordered);
        }

        $warehouse->activate();
        $this->warehouses->save($warehouse);

        return $details;
    }
}
