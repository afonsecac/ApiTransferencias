<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Market\Inventario\Application\WarehouseDetails;

/**
 * Respuesta de los endpoints de almacenes (estructura de presentación, no es de entrada).
 */
class WarehouseOutDto
{
    /**
     * @param array{id: int, name: string}|null $owner
     * @param array{id: int, code: string, name: string}|null $costCenter
     * @param array{id: int, name: string, provinceName: string, selectable: bool}|null $municipality
     * @param array{setsCoverage: bool, assemblesCombos: bool, seesSupplyCost: bool} $permissions
     * @param list<string> $missing
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $address,
        public string $status,
        public bool $isMain,
        public ?string $regime,
        public ?array $owner,
        public ?array $costCenter,
        public ?array $municipality,
        public array $permissions,
        public array $missing,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(WarehouseDetails $details): self
    {
        $w = $details->warehouse;
        $center = $details->costCenter();
        $m = $details->municipality;

        return new self(
            (int) $w->id(),
            $w->slug()->value(),
            $w->name(),
            $w->address(),
            $w->status()->value,
            $w->isMain(),
            $details->regime,
            $details->owner === null ? null : ['id' => $details->owner->id, 'name' => $details->owner->name],
            $center === null ? null : ['id' => $center->id, 'code' => $center->code, 'name' => $center->name],
            $m === null ? null : ['id' => $m->id, 'name' => $m->name, 'provinceName' => $m->provinceName, 'selectable' => $m->selectable],
            [
                'setsCoverage' => $w->permissions()->setsCoverage,
                'assemblesCombos' => $w->permissions()->assemblesCombos,
                'seesSupplyCost' => $w->permissions()->seesSupplyCost,
            ],
            $details->missing,
            $w->createdAt()->format(DATE_ATOM),
            $w->updatedAt()->format(DATE_ATOM),
        );
    }
}
