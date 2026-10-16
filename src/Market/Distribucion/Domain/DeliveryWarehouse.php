<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/** Un almacén tal como lo ve este contexto; `status` es `draft`, `active` o `inactive`. */
final readonly class DeliveryWarehouse
{
    public function __construct(public int $id, public string $name, public string $status)
    {
    }
}
