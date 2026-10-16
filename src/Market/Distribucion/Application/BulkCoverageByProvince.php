<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\CoverageConfirmationRequired;
use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\CoverageWarehouseInvalid;
use App\Market\Distribucion\Domain\DeliveryCoverage;
use App\Market\Distribucion\Domain\DeliveryMunicipalities;
use App\Market\Distribucion\Domain\DeliveryWarehouses;
use App\Market\Distribucion\Domain\InvalidCoverage;

final class BulkCoverageByProvince
{
    public function __construct(
        private readonly CoverageRepository $coverage,
        private readonly DeliveryWarehouses $warehouses,
        private readonly DeliveryMunicipalities $municipalities,
    ) {
    }

    /**
     * Alta masiva por provincia: crea o actualiza el precio en todos los municipios activos de la provincia. Sin `$apply` solo cuenta
     * («Crear 7 · Actualizar 8») para mostrarlo antes; los municipios ya cubiertos no se sobrescriben salvo con `$overwrite`.
     *
     * @throws CoverageWarehouseInvalid
     * @throws InvalidCoverage
     * @throws CoverageConfirmationRequired al aplicar, una tarifa 0 o un máximo vacío hay que confirmarlos
     */
    public function __invoke(int $warehouseId, int $provinceId, CoverageInput $input, bool $overwrite, bool $apply): BulkResult
    {
        $warehouse = $this->warehouses->find($warehouseId) ?? throw CoverageWarehouseInvalid::unknown($warehouseId);
        if ($warehouse->status !== 'active') {
            throw $warehouse->status === 'draft' ? CoverageWarehouseInvalid::draft($warehouse->name) : CoverageWarehouseInvalid::notActive($warehouse->name);
        }
        $rates = $input->rates();
        if ($apply) {
            if ($rates->hasZeroRate() && !$input->confirmZeroRate) {
                throw CoverageConfirmationRequired::zeroRate();
            }
            if ($rates->hasNoMax() && !$input->confirmNoMax) {
                throw CoverageConfirmationRequired::noMax();
            }
        }

        $create = $update = $skipped = 0;
        foreach ($this->municipalities->selectableOfProvince($provinceId) as $municipality) {
            $existing = $this->coverage->find($warehouseId, $municipality->id);
            if ($existing === null) {
                ++$create;
                if ($apply) {
                    $this->coverage->save(DeliveryCoverage::create($warehouseId, $municipality->id, $rates, $input->priority));
                }
            } elseif ($overwrite) {
                ++$update;
                if ($apply) {
                    $existing->reprice($rates, $input->priority);
                    $existing->resume();
                    $this->coverage->save($existing);
                }
            } else {
                ++$skipped;
            }
        }

        return new BulkResult($create, $update, $skipped, $apply);
    }
}
