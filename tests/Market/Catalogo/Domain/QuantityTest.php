<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * COMR-141: todo se guarda en la unidad base (gramos, mililitros, unidades) y la unidad original se guarda aparte
 * solo para mostrarla y editarla.
 *
 * @covers \App\Market\Catalogo\Domain\Quantity
 * @covers \App\Market\Catalogo\Domain\MeasureUnit
 */
final class QuantityTest extends TestCase
{
    /**
     * @return iterable<string, array{float, MeasureUnit, float}>
     */
    public static function conversions(): iterable
    {
        yield '1 kg son 1000 g' => [1.0, MeasureUnit::KG, 1000.0];
        yield '2,5 lb son 1133,981 g' => [2.5, MeasureUnit::LB, 1133.981];
        yield '1 oz son 28,35 g (redondeado a 3 decimales)' => [1.0, MeasureUnit::OZ, 28.35];
        yield '500 g quedan en 500 g' => [500.0, MeasureUnit::G, 500.0];
        yield '0,5 l son 500 ml' => [0.5, MeasureUnit::L, 500.0];
        yield '1 fl oz son 29,574 ml' => [1.0, MeasureUnit::FL_OZ, 29.574];
        yield '12 unidades quedan en 12' => [12.0, MeasureUnit::UNIT, 12.0];
    }

    /**
     * @dataProvider conversions
     */
    public function testItStoresTheValueInTheBaseUnit(float $value, MeasureUnit $unit, float $base): void
    {
        $quantity = Quantity::of($value, $unit);

        $this->assertEqualsWithDelta($base, $quantity->baseValue(), 0.0001);
        $this->assertSame($unit, $quantity->unit());
        $this->assertEqualsWithDelta($value, $quantity->originalValue(), 0.0001);
    }

    public function testEachUnitBelongsToItsKind(): void
    {
        $this->assertSame(MeasureKind::WEIGHT, MeasureUnit::LB->kind());
        $this->assertSame(MeasureKind::VOLUME, MeasureUnit::FL_OZ->kind());
        $this->assertSame(MeasureKind::COUNT, MeasureUnit::UNIT->kind());
        $this->assertSame(MeasureUnit::G, MeasureKind::WEIGHT->baseUnit());
        $this->assertSame(MeasureUnit::ML, MeasureKind::VOLUME->baseUnit());
        $this->assertSame(MeasureUnit::UNIT, MeasureKind::COUNT->baseUnit());
    }

    public function testItConvertsBetweenUnitsOfTheSameKind(): void
    {
        $quantity = Quantity::of(2.5, MeasureUnit::LB);

        $this->assertEqualsWithDelta(2.5, $quantity->in(MeasureUnit::LB), 0.0001);
        $this->assertEqualsWithDelta(1.134, $quantity->in(MeasureUnit::KG), 0.0005);
        $this->assertEqualsWithDelta(40.0, $quantity->in(MeasureUnit::OZ), 0.001);
    }

    public function testItRefusesToConvertAcrossKinds(): void
    {
        $this->expectException(InvalidProduct::class);

        Quantity::of(1.0, MeasureUnit::KG)->in(MeasureUnit::L);
    }

    /**
     * @return iterable<string, array{float, MeasureUnit}>
     */
    public static function invalid(): iterable
    {
        yield 'cero' => [0.0, MeasureUnit::G];
        yield 'negativo' => [-1.0, MeasureUnit::KG];
        yield 'tan pequeño que redondea a cero' => [0.0000001, MeasureUnit::G];
        yield 'unidades con decimales' => [2.5, MeasureUnit::UNIT];
        yield 'no es un número' => [NAN, MeasureUnit::G];
        yield 'infinito' => [INF, MeasureUnit::G];
    }

    /**
     * @dataProvider invalid
     */
    public function testItRejectsAValueThatIsNotAPositiveAmount(float $value, MeasureUnit $unit): void
    {
        $this->expectException(InvalidProduct::class);

        Quantity::of($value, $unit);
    }

    public function testItIsRebuiltFromWhatWasStored(): void
    {
        $quantity = Quantity::fromStored(1133.981, 2.5, MeasureUnit::LB);

        $this->assertSame(1133.981, $quantity->baseValue());
        $this->assertSame(2.5, $quantity->originalValue());
        $this->assertSame(MeasureUnit::LB, $quantity->unit());
    }

    public function testUnitsAreFoundByTheirCode(): void
    {
        $this->assertSame(MeasureUnit::FL_OZ, MeasureUnit::tryFrom('fl_oz'));
        $this->assertNull(MeasureUnit::tryFrom('stone'));
    }
}
