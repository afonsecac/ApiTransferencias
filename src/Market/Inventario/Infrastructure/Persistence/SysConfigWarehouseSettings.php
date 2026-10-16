<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\Market\Inventario\Domain\WarehouseSettings;
use App\Repository\SysConfigRepository;

final class SysConfigWarehouseSettings implements WarehouseSettings
{
    public const DEFAULT_MUNICIPALITY_KEY = 'market.warehouse.default_municipality';

    public function __construct(private readonly SysConfigRepository $config)
    {
    }

    public function defaultMunicipalityCode(): ?string
    {
        $value = $this->config->findCachedValue(self::DEFAULT_MUNICIPALITY_KEY, true);

        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
