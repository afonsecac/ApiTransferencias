<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\CoverageConfirmationRequired;
use App\Market\Distribucion\Domain\CoverageMunicipalityInvalid;
use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\CoverageWarehouseInvalid;
use App\Market\Distribucion\Domain\DeliveryCoverage;
use App\Market\Distribucion\Domain\DeliveryCurrencies;
use App\Market\Distribucion\Domain\DeliveryMunicipalities;
use App\Market\Distribucion\Domain\DeliveryWarehouses;
use App\Market\Distribucion\Domain\InvalidCoverage;

final class SetCoverage
{
    public function __construct(
        private readonly CoverageRepository $coverage,
        private readonly DeliveryWarehouses $warehouses,
        private readonly DeliveryMunicipalities $municipalities,
        private readonly DeliveryCurrencies $currencies,
    ) {
    }

    /**
     * Crea o actualiza la cobertura de un municipio. Empezar a cubrir exige el almacén y el municipio activos; una cobertura que ya
     * existe se puede seguir editando aunque el almacén se haya desactivado o el municipio ya no sea elegible (se conserva).
     *
     * @throws CoverageWarehouseInvalid
     * @throws CoverageMunicipalityInvalid
     * @throws InvalidCoverage
     * @throws CoverageConfirmationRequired una tarifa 0 o un máximo vacío hay que confirmarlos
     */
    public function __invoke(int $warehouseId, int $municipalityId, CoverageInput $input): CoverageRow
    {
        $warehouse = $this->warehouses->find($warehouseId) ?? throw CoverageWarehouseInvalid::unknown($warehouseId);
        if ($warehouse->status === 'draft') {
            throw CoverageWarehouseInvalid::draft($warehouse->name);
        }
        $existing = $this->coverage->find($warehouseId, $municipalityId);
        $municipality = $this->municipalities->find($municipalityId);
        if ($existing === null) {
            if ($warehouse->status !== 'active') {
                throw CoverageWarehouseInvalid::notActive($warehouse->name);
            }
            if ($municipality === null) {
                throw CoverageMunicipalityInvalid::unknown($municipalityId);
            }
            if (!$municipality->selectable) {
                throw CoverageMunicipalityInvalid::notSelectable($municipality->name);
            }
        }

        $rates = $input->rates();
        if ($rates->hasZeroRate() && !$input->confirmZeroRate) {
            throw CoverageConfirmationRequired::zeroRate();
        }
        if ($rates->hasNoMax() && !$input->confirmNoMax) {
            throw CoverageConfirmationRequired::noMax();
        }

        if ($existing === null) {
            $existing = DeliveryCoverage::create($warehouseId, $municipalityId, $rates, $input->priority);
        } else {
            $existing->reprice($rates, $input->priority);
        }
        $this->coverage->save($existing);

        return new CoverageRow($existing, $municipality, $this->currencies->currencyOf($warehouseId));
    }
}
