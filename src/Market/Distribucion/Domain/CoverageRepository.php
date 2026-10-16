<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

interface CoverageRepository
{
    public function find(int $warehouseId, int $municipalityId): ?DeliveryCoverage;

    /** @return list<DeliveryCoverage> */
    public function ofWarehouse(int $warehouseId): array;

    /** @return list<DeliveryCoverage> las filas activas de cualquier almacén en ese municipio */
    public function activeInMunicipality(int $municipalityId): array;

    public function save(DeliveryCoverage $coverage): void;
}
