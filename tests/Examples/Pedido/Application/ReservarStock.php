<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Application;

use App\Tests\Examples\Pedido\Domain\Cantidad;

/** Caso de uso de ejemplo: depende del puerto, nunca de Doctrine ni del framework. */
final class ReservarStock
{
    public function __construct(private readonly StockPort $stock)
    {
    }

    public function __invoke(string $sku, Cantidad $cantidad): bool
    {
        return $this->stock->reservar($sku, $cantidad);
    }
}
