<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseRepository;

/**
 * El puerto de consulta que Inventario ofrece a los demás contextos: «qué almacenes pueden vender». Solo los activos. Cobertura,
 * selección de almacén y reservas lo usan para no repetir la regla; lo ya vendido no se vuelve a consultar contra ella.
 */
final class SellableWarehouseIds
{
    public function __construct(private readonly WarehouseRepository $warehouses)
    {
    }

    /** @return list<int> */
    public function __invoke(): array
    {
        return array_values(array_map(
            static fn ($w) => (int) $w->id(),
            array_filter($this->warehouses->findAll(), static fn ($w) => $w->isActive()),
        ));
    }
}
