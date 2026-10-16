<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\DeliveryMunicipalities;
use App\Market\Distribucion\Domain\DeliveryMunicipality;

final class FakeDeliveryMunicipalities implements DeliveryMunicipalities
{
    /** @var array<int, DeliveryMunicipality> */
    public array $items = [];

    public function add(int $id, string $name, int $provinceId = 1, string $province = 'La Habana', bool $selectable = true): void
    {
        $this->items[$id] = new DeliveryMunicipality($id, $name, $provinceId, $province, $selectable);
    }

    public function find(int $id): ?DeliveryMunicipality
    {
        return $this->items[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        return array_intersect_key($this->items, array_flip($ids));
    }

    public function selectableOfProvince(int $provinceId): array
    {
        return array_values(array_filter($this->items, static fn (DeliveryMunicipality $m) => $m->provinceId === $provinceId && $m->selectable));
    }
}
