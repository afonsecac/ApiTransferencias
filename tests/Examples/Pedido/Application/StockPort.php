<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Application;

use App\Tests\Examples\Pedido\Domain\Cantidad;

/** Puerto de salida de ejemplo: lo implementa Infrastructure y lo sustituye un doble en memoria en los tests. */
interface StockPort
{
    public function disponible(string $sku): int;

    /** Devuelve true solo si había stock suficiente y lo ha reservado. */
    public function reservar(string $sku, Cantidad $cantidad): bool;
}
