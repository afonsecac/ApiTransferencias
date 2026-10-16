<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Market\Inventario\Application\StockLineDetails;

/**
 * Una línea de existencias (estructura de presentación, no es de entrada). `available` es 0 en un almacén que no está activo,
 * pero `reserved` se sigue mostrando.
 */
class StockLineOutDto
{
    /**
     * @param array{id: int, name: string, status: string}|null $warehouse
     * @param array{id: int, sku: string, name: string, seller: string}|null $product
     */
    public function __construct(
        public int $id,
        public int $warehouseId,
        public ?array $warehouse,
        public string $itemType,
        public int $itemId,
        public ?array $product,
        public int $onHand,
        public int $reserved,
        public int $safety,
        public int $available,
        public bool $low,
        public bool $warehouseActive,
        public string $updatedAt,
    ) {
    }

    public static function from(StockLineDetails $d): self
    {
        $l = $d->line;
        $w = $d->warehouse;
        $p = $d->product;

        return new self(
            (int) $l->id(),
            $l->warehouseId(),
            $w === null ? null : ['id' => (int) $w->id(), 'name' => $w->name(), 'status' => $w->status()->value],
            $l->itemType()->value,
            $l->itemId(),
            $p === null ? null : ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'seller' => $p->sellerName],
            $l->onHand(),
            $l->reserved(),
            $l->safety(),
            $d->available,
            $d->low,
            $d->warehouseActive,
            $l->updatedAt()->format(DATE_ATOM),
        );
    }
}
