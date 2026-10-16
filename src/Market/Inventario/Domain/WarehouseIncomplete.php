<?php

declare(strict_types=1);

namespace App\Market\Inventario\Domain;

final class WarehouseIncomplete extends \DomainException
{
    /** @param list<string> $missing `owner`, `cost_center` y/o `municipality` */
    public function __construct(private readonly array $missing)
    {
        parent::__construct('El almacén no está completo para activarse: falta ' . implode(', ', $missing) . '.');
    }

    /** @return list<string> */
    public function missing(): array
    {
        return $this->missing;
    }
}
