<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\CoverageWarehouseInvalid;
use App\Market\Distribucion\Domain\DeliveryCoverage;
use App\Market\Distribucion\Domain\DeliveryCurrencies;
use App\Market\Distribucion\Domain\DeliveryMunicipalities;
use App\Market\Distribucion\Domain\DeliveryWarehouses;

final class ListCoverage
{
    public function __construct(
        private readonly CoverageRepository $coverage,
        private readonly DeliveryCurrencies $currencies,
        private readonly DeliveryWarehouses $warehouses,
        private readonly DeliveryMunicipalities $municipalities,
    ) {
    }

    /**
     * La cobertura de un almacén por municipio, con lo que la pantalla avisa: si el almacén atiende ese municipio, si hay empate de
     * prioridad y si un almacén de respaldo es más caro que el que atiende.
     *
     * @throws CoverageWarehouseInvalid
     */
    public function __invoke(int $warehouseId): CoverageListing
    {
        $this->warehouses->find($warehouseId) ?? throw CoverageWarehouseInvalid::unknown($warehouseId);
        $currency = $this->currencies->currencyOf($warehouseId);
        $mine = $this->coverage->ofWarehouse($warehouseId);
        $municipalities = $this->municipalities->findMany(array_map(static fn (DeliveryCoverage $c) => $c->municipalityId(), $mine));
        $sellable = $this->warehouses->sellableIds();

        $rows = [];
        foreach ($mine as $c) {
            $candidates = array_values(array_filter(
                $this->coverage->activeInMunicipality($c->municipalityId()),
                static fn (DeliveryCoverage $o) => in_array($o->warehouseId(), $sellable, true),
            ));
            $competes = $c->isActive() && in_array($warehouseId, $sellable, true);
            $primary = $competes ? self::primary($candidates) : null;
            $isServing = $primary !== null && $primary->warehouseId() === $warehouseId;
            $tie = $primary !== null && $c->priority() === $primary->priority()
                && count(array_filter($candidates, static fn (DeliveryCoverage $o) => $o->priority() === $primary->priority())) > 1;
            $moreExpensive = $primary !== null && !$isServing
                && ($c->rates()->perKg->isGreaterThan($primary->rates()->perKg) || $c->rates()->perL->isGreaterThan($primary->rates()->perL));

            $rows[] = new CoverageRow($c, $municipalities[$c->municipalityId()] ?? null, $currency, $isServing, $tie, $moreExpensive);
        }
        usort($rows, static fn (CoverageRow $a, CoverageRow $b) => [mb_strtolower($a->municipality->name ?? ''), $a->coverage->municipalityId()] <=> [mb_strtolower($b->municipality->name ?? ''), $b->coverage->municipalityId()]);

        return new CoverageListing($currency, $rows);
    }

    /** @param list<DeliveryCoverage> $candidates */
    public static function primary(array $candidates): ?DeliveryCoverage
    {
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn (DeliveryCoverage $a, DeliveryCoverage $b) => [$a->priority(), $a->warehouseId()] <=> [$b->priority(), $b->warehouseId()]);

        return $candidates[0];
    }
}
