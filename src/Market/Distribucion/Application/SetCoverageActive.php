<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\CoverageNotFound;
use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\DeliveryCurrencies;
use App\Market\Distribucion\Domain\DeliveryMunicipalities;

final class SetCoverageActive
{
    public function __construct(
        private readonly CoverageRepository $coverage,
        private readonly DeliveryMunicipalities $municipalities,
        private readonly DeliveryCurrencies $currencies,
    ) {
    }

    /**
     * «Dejar de cubrir» desactiva la fila, no la borra.
     *
     * @throws CoverageNotFound
     */
    public function __invoke(int $warehouseId, int $municipalityId, bool $active): CoverageRow
    {
        $coverage = $this->coverage->find($warehouseId, $municipalityId) ?? throw CoverageNotFound::for($warehouseId, $municipalityId);
        $active ? $coverage->resume() : $coverage->stop();
        $this->coverage->save($coverage);

        return new CoverageRow($coverage, $this->municipalities->find($municipalityId), $this->currencies->currencyOf($warehouseId));
    }
}
