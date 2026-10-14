<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\InvalidProduct;
use PHPUnit\Framework\TestCase;

/**
 * COMR-101: los importes del mercado son decimales de dos decimales, nunca float.
 *
 * @covers \App\Market\Catalogo\Domain\Amount
 */
final class AmountTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function valid(): iterable
    {
        yield 'entero' => ['25', '25.00', 2500];
        yield 'un decimal' => ['25.5', '25.50', 2550];
        yield 'dos decimales' => ['100.05', '100.05', 10005];
        yield 'céntimos' => ['0.01', '0.01', 1];
        yield 'con espacios' => [' 10.00 ', '10.00', 1000];
    }

    /**
     * @dataProvider valid
     */
    public function testItNormalisesToTwoDecimalsAndKeepsMinorUnits(string $input, string $text, int $minor): void
    {
        $amount = Amount::fromString($input);

        $this->assertSame($text, $amount->toString());
        $this->assertSame($minor, $amount->minorUnits());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'vacío' => [''];
        yield 'cero' => ['0'];
        yield 'cero con decimales' => ['0.00'];
        yield 'negativo' => ['-5'];
        yield 'tres decimales' => ['1.234'];
        yield 'coma decimal' => ['1,50'];
        yield 'texto' => ['abc'];
        yield 'notación científica' => ['1e3'];
        yield 'demasiado grande' => ['10000000000000'];
    }

    /**
     * @dataProvider invalid
     */
    public function testItRejectsAnythingThatIsNotAPositiveAmountOfTwoDecimals(string $input): void
    {
        $this->expectException(InvalidProduct::class);

        Amount::fromString($input);
    }

    public function testItComparesInDecimalWithoutFloatErrors(): void
    {
        $this->assertTrue(Amount::fromString('0.30')->isLessThan(Amount::fromString('0.31')));
        $this->assertFalse(Amount::fromString('0.1')->isLessThan(Amount::fromString('0.10')));
        $this->assertTrue(Amount::fromString('0.10')->equals(Amount::fromString('0.1')));
        // 0,1 + 0,2 en float no es 0,3: en decimal sí.
        $this->assertSame(30, Amount::fromString('0.1')->minorUnits() + Amount::fromString('0.2')->minorUnits());
    }
}
