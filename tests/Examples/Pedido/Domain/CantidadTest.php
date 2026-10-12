<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Domain;

use PHPUnit\Framework\TestCase;

/**
 * Convención para Domain: TestCase puro, sin kernel, sin base de datos y sin dobles de infraestructura.
 */
final class CantidadTest extends TestCase
{
    public function testAcceptsAPositiveAmount(): void
    {
        self::assertSame(3, (new Cantidad(3))->valor);
    }

    public function testRejectsZeroAndNegativeAmounts(): void
    {
        $this->expectException(\DomainException::class);
        new Cantidad(0);
    }

    public function testAddsTwoAmountsWithoutMutatingEither(): void
    {
        $a = new Cantidad(2);
        $b = new Cantidad(5);

        self::assertSame(7, $a->sumar($b)->valor);
        self::assertSame(2, $a->valor);
    }
}
