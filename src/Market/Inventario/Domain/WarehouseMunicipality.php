<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

/** El municipio de un almacén tal como lo ve este contexto: `selectable` incluye el estado de su provincia. */
final readonly class WarehouseMunicipality
{
    public function __construct(public int $id, public string $code, public string $name, public string $provinceName, public bool $selectable)
    {
    }
}
