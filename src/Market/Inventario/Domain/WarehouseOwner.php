<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/**
 * La unidad dueña de un almacén tal como la ve este contexto. El régimen (de la plataforma o de socio) se deduce de sus
 * tipos, sin campo propio: si es a la vez `platform` y `warehouse_partner`, manda la plataforma.
 */
final readonly class WarehouseOwner
{
    public const REGIME_PLATFORM = 'platform';
    public const REGIME_PARTNER = 'partner';

    /**
     * @param list<string> $types
     * @param list<WarehouseCostCenter> $costCenters
     */
    public function __construct(public int $id, public string $name, public array $types, public array $costCenters)
    {
    }

    public function regime(): ?string
    {
        return match (true) {
            in_array('platform', $this->types, true) => self::REGIME_PLATFORM,
            in_array('warehouse_partner', $this->types, true) => self::REGIME_PARTNER,
            default => null,
        };
    }

    public function principalCostCenter(): ?WarehouseCostCenter
    {
        foreach ($this->costCenters as $center) {
            if ($center->principal && $center->active) {
                return $center;
            }
        }

        return null;
    }

    public function activeCostCenter(int $id): ?WarehouseCostCenter
    {
        foreach ($this->costCenters as $center) {
            if ($center->id === $id && $center->active) {
                return $center;
            }
        }

        return null;
    }
}
