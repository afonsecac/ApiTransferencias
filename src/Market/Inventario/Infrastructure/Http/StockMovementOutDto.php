<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Http;

use App\Market\Inventario\Application\StockMovementDetails;

/**
 * Un movimiento del libro de stock (estructura de presentación, no es de entrada). La cantidad lleva signo.
 */
class StockMovementOutDto
{
    /**
     * @param array{id: int, name: string}|null $warehouse
     * @param array{id: int, sku: string, name: string}|null $product
     */
    public function __construct(
        public int $id,
        public int $warehouseId,
        public ?array $warehouse,
        public string $itemType,
        public int $itemId,
        public ?array $product,
        public string $type,
        public int $quantity,
        public int $onHandAfter,
        public string $reason,
        public string $comment,
        public ?string $reference,
        public string $actorName,
        public int $ownerCostCenterId,
        public int $custodianCostCenterId,
        public string $createdAt,
    ) {
    }

    public static function from(StockMovementDetails $d): self
    {
        $m = $d->movement;
        $w = $d->warehouse;
        $p = $d->product;

        return new self(
            (int) $m->id(),
            $m->warehouseId(),
            $w === null ? null : ['id' => (int) $w->id(), 'name' => $w->name()],
            $m->itemType()->value,
            $m->itemId(),
            $p === null ? null : ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name],
            $m->type(),
            $m->quantity(),
            $m->onHandAfter(),
            $m->reason()->value,
            $m->comment(),
            $m->reference(),
            $m->actorName(),
            $m->ownerCostCenterId(),
            $m->custodianCostCenterId(),
            $m->createdAt()->format(DATE_ATOM),
        );
    }
}
