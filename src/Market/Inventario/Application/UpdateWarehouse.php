<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\InvalidWarehouse;
use App\Market\Inventario\Domain\MainWarehouseProtected;
use App\Market\Inventario\Domain\PartnerPermissions;
use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseMunicipalityInvalid;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseOwnerInvalid;
use App\Market\Inventario\Domain\WarehouseOwners;
use App\Market\Inventario\Domain\WarehouseRepository;

final class UpdateWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseOwners $owners,
        private readonly WarehouseMunicipalities $municipalities,
    ) {
    }

    /**
     * El identificador no cambia. El dueño y el municipio se validan solo cuando se eligen o cambian: un almacén cuyo municipio se
     * desactivó después se puede seguir editando (p. ej. renombrar) sin tocarlo.
     *
     * @throws WarehouseNotFound
     * @throws InvalidWarehouse
     * @throws WarehouseOwnerInvalid
     * @throws WarehouseMunicipalityInvalid
     * @throws MainWarehouseProtected
     */
    public function __invoke(int $id, WarehouseInput $input): WarehouseDetails
    {
        $warehouse = $this->warehouses->findById($id) ?? throw WarehouseNotFound::forId($id);

        // Todo se valida antes de mutar: un rechazo no deja el almacén a medias.
        $municipalityChanged = $input->municipalityId !== null && $input->municipalityId !== $warehouse->municipalityId();
        if ($municipalityChanged) {
            $municipality = $this->municipalities->find((int) $input->municipalityId) ?? throw WarehouseMunicipalityInvalid::unknown((int) $input->municipalityId);
            if (!$municipality->selectable) {
                throw WarehouseMunicipalityInvalid::notSelectable($municipality->name);
            }
        }

        $sameOwner = $input->orgUnitId !== null && $input->orgUnitId === $warehouse->orgUnitId() && ($input->costCenterId === null || $input->costCenterId === $warehouse->costCenterId());
        $costCenterId = $warehouse->costCenterId();
        $regime = null;
        if ($input->orgUnitId !== null) {
            $owner = $this->owners->find($input->orgUnitId) ?? throw WarehouseOwnerInvalid::unknown($input->orgUnitId);
            $regime = $owner->regime();
            if (!$sameOwner) {
                if ($regime === null) {
                    throw WarehouseOwnerInvalid::notAllowed($owner->name);
                }
                $center = $input->costCenterId === null ? $owner->principalCostCenter() : $owner->activeCostCenter($input->costCenterId);
                if ($center === null) {
                    throw WarehouseOwnerInvalid::costCenter($input->costCenterId ?? 0, $owner->name);
                }
                $costCenterId = $center->id;
            }
        }

        $warehouse->rename($input->name);
        $warehouse->changeAddress($input->address);
        if ($municipalityChanged || $input->municipalityId === null) {
            $warehouse->locate($input->municipalityId);
        }
        $warehouse->assignOwner($input->orgUnitId, $input->orgUnitId === null ? null : $costCenterId);
        // En un almacén de la plataforma los permisos del socio no aplican y se ignoran.
        $warehouse->grant($regime === 'platform' ? PartnerPermissions::none() : $input->permissions);
        $this->warehouses->save($warehouse);

        return WarehouseDetails::of($warehouse, $this->owners, $this->municipalities);
    }
}
