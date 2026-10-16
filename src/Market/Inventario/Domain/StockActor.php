<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** Quién hace un movimiento: su id (si la tiene) y el nombre con que se muestra. */
final readonly class StockActor
{
    public function __construct(public ?int $id, public string $name)
    {
    }
}
