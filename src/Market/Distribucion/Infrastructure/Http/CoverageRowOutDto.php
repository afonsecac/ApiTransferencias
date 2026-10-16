<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Http;

use App\Market\Distribucion\Application\CoverageRow;

/**
 * Una fila de cobertura (estructura de presentación, no es de entrada). Los importes van como texto decimal con 4 decimales.
 */
class CoverageRowOutDto
{
    /**
     * @param array{id: int, name: string, provinceId: int, provinceName: string, selectable: bool}|null $municipality
     */
    public function __construct(
        public int $warehouseId,
        public int $municipalityId,
        public ?array $municipality,
        public string $rateMode,
        public string $ratePerKg,
        public string $ratePerL,
        public string $minPrice,
        public ?string $maxPrice,
        public string $currency,
        public int $priority,
        public bool $active,
        public bool $isServing,
        public bool $tie,
        public bool $backupMoreExpensive,
        public string $updatedAt,
    ) {
    }

    public static function from(CoverageRow $row): self
    {
        $c = $row->coverage;
        $m = $row->municipality;
        $rates = $c->rates();

        return new self(
            $c->warehouseId(),
            $c->municipalityId(),
            $m === null ? null : ['id' => $m->id, 'name' => $m->name, 'provinceId' => $m->provinceId, 'provinceName' => $m->provinceName, 'selectable' => $m->selectable],
            $c->rateMode(),
            $rates->perKg->value(),
            $rates->perL->value(),
            $rates->min->value(),
            $rates->max?->value(),
            $row->currency,
            $c->priority(),
            $c->isActive(),
            $row->isServing,
            $row->tie,
            $row->backupMoreExpensive,
            $c->updatedAt()->format(DATE_ATOM),
        );
    }
}
