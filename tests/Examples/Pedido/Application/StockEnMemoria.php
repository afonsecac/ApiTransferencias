<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Application;

use App\Tests\Examples\Pedido\Domain\Cantidad;

/** Puerto en memoria para probar Application sin base de datos. */
final class StockEnMemoria implements StockPort
{
    /** @param array<string, int> $stock */
    public function __construct(private array $stock)
    {
    }

    public function disponible(string $sku): int
    {
        return $this->stock[$sku] ?? 0;
    }

    public function reservar(string $sku, Cantidad $cantidad): bool
    {
        if ($this->disponible($sku) < $cantidad->valor) {
            return false;
        }
        $this->stock[$sku] -= $cantidad->valor;

        return true;
    }
}
