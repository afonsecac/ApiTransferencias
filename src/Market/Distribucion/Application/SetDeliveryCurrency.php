<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\CoverageWarehouseInvalid;
use App\Market\Distribucion\Domain\DeliveryCurrencies;
use App\Market\Distribucion\Domain\DeliveryWarehouses;
use App\Market\Distribucion\Domain\InvalidCoverage;

final class SetDeliveryCurrency
{
    public function __construct(
        private readonly DeliveryWarehouses $warehouses,
        private readonly DeliveryCurrencies $currencies,
    ) {
    }

    /**
     * Una sola moneda por almacén para todas sus tarifas.
     *
     * @throws CoverageWarehouseInvalid
     * @throws InvalidCoverage
     */
    public function __invoke(int $warehouseId, string $currency): string
    {
        $this->warehouses->find($warehouseId) ?? throw CoverageWarehouseInvalid::unknown($warehouseId);
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidCoverage(sprintf('La moneda "%s" no es válida: tres letras (p. ej. EUR).', $currency));
        }
        $this->currencies->set($warehouseId, $currency);

        return $currency;
    }
}
