<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\CoverageRepository;
use App\Market\Distribucion\Domain\DeliveryCoverage;

final class InMemoryCoverageRepository implements CoverageRepository
{
    /** @var array<string, DeliveryCoverage> */
    public array $items = [];
    private int $nextId = 1;

    public function find(int $warehouseId, int $municipalityId): ?DeliveryCoverage
    {
        return $this->items[$warehouseId . ':' . $municipalityId] ?? null;
    }

    public function ofWarehouse(int $warehouseId): array
    {
        return array_values(array_filter($this->items, static fn (DeliveryCoverage $c) => $c->warehouseId() === $warehouseId));
    }

    public function activeInMunicipality(int $municipalityId): array
    {
        return array_values(array_filter($this->items, static fn (DeliveryCoverage $c) => $c->municipalityId() === $municipalityId && $c->isActive()));
    }

    public function save(DeliveryCoverage $coverage): void
    {
        if ($coverage->id() === null) {
            (new \ReflectionProperty($coverage, 'id'))->setValue($coverage, $this->nextId++);
        }
        $this->items[$coverage->warehouseId() . ':' . $coverage->municipalityId()] = $coverage;
    }
}
