<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Application;

use App\Tests\Examples\Pedido\Domain\Cantidad;
use PHPUnit\Framework\TestCase;

/**
 * Convención para Application: TestCase con puertos en memoria. Se prueba la decisión del caso de uso,
 * no cómo se persiste.
 */
final class ReservarStockTest extends TestCase
{
    public function testReservesWhenThereIsEnoughStock(): void
    {
        $stock = new StockEnMemoria(['arroz' => 10]);

        $resultado = (new ReservarStock($stock))('arroz', new Cantidad(4));

        self::assertTrue($resultado);
        self::assertSame(6, $stock->disponible('arroz'));
    }

    public function testDoesNotReserveMoreThanAvailable(): void
    {
        $stock = new StockEnMemoria(['arroz' => 3]);

        $resultado = (new ReservarStock($stock))('arroz', new Cantidad(4));

        self::assertFalse($resultado);
        self::assertSame(3, $stock->disponible('arroz'));
    }
}
