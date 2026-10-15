<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureSystem;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * COMR-141 y COMR-281: todo se guarda en la unidad base (por defecto kilogramos, litros y unidades; la configuración puede
 * decir otra) y la unidad original se guarda aparte solo para mostrarla y editarla.
 *
 * @covers \App\Market\Catalogo\Domain\Quantity
 * @covers \App\Market\Catalogo\Domain\MeasureUnit
 * @covers \App\Market\Catalogo\Domain\MeasureSystem
 */
final class QuantityTest extends TestCase
{
    /**
     * @return iterable<string, array{float, MeasureUnit, float}>
     */
    public static function conversions(): iterable
    {
        yield '1 kg es 1 kg' => [1.0, MeasureUnit::KG, 1.0];
        yield '2,5 lb son 1,133981 kg' => [2.5, MeasureUnit::LB, 1.133981];
        yield '1 oz son 0,02835 kg (6 decimales)' => [1.0, MeasureUnit::OZ, 0.02835];
        yield '500 g son 0,5 kg' => [500.0, MeasureUnit::G, 0.5];
        yield '2,5 g son 0,0025 kg: ya no se pierde' => [2.5, MeasureUnit::G, 0.0025];
        yield '0,5 l quedan en 0,5 l' => [0.5, MeasureUnit::L, 0.5];
        yield '330 ml son 0,33 l' => [330.0, MeasureUnit::ML, 0.33];
        yield '1 fl oz son 0,029574 l' => [1.0, MeasureUnit::FL_OZ, 0.029574];
        yield '12 unidades quedan en 12' => [12.0, MeasureUnit::UNIT, 12.0];
        yield '1 t son 1000 kg' => [1.0, MeasureUnit::T, 1000.0];
        yield '0,5 t son 500 kg' => [0.5, MeasureUnit::T, 500.0];
        yield '1 gal US son 3,785412 l' => [1.0, MeasureUnit::GAL, 3.785412];
        yield '3 pares son 6 unidades' => [3.0, MeasureUnit::PAIR, 6.0];
        yield '2 docenas son 24 unidades' => [2.0, MeasureUnit::DOZEN, 24.0];
    }

    /**
     * @dataProvider conversions
     */
    public function testItStoresTheValueInTheBaseUnit(float $value, MeasureUnit $unit, float $base): void
    {
        $quantity = Quantity::of($value, $unit);

        $this->assertEqualsWithDelta($base, $quantity->baseValue(), 0.0000001);
        $this->assertSame($unit, $quantity->unit());
        $this->assertEqualsWithDelta($value, $quantity->originalValue(), 0.0001);
    }

    public function testEachUnitBelongsToItsKind(): void
    {
        $this->assertSame(MeasureKind::WEIGHT, MeasureUnit::LB->kind());
        $this->assertSame(MeasureKind::VOLUME, MeasureUnit::FL_OZ->kind());
        $this->assertSame(MeasureKind::COUNT, MeasureUnit::UNIT->kind());
        $this->assertSame(MeasureKind::WEIGHT, MeasureUnit::T->kind());
        $this->assertSame(MeasureKind::VOLUME, MeasureUnit::GAL->kind());
        $this->assertSame(MeasureKind::COUNT, MeasureUnit::PAIR->kind());
        $this->assertSame(MeasureKind::COUNT, MeasureUnit::DOZEN->kind());
    }

    public function testTheStandardSystemStoresKilogramsLitersAndUnits(): void
    {
        $system = MeasureSystem::standard();

        $this->assertSame(MeasureUnit::KG, $system->baseUnit(MeasureKind::WEIGHT));
        $this->assertSame(MeasureUnit::L, $system->baseUnit(MeasureKind::VOLUME));
        $this->assertSame(MeasureUnit::UNIT, $system->baseUnit(MeasureKind::COUNT));
    }

    public function testASystemRefusesABaseOfTheWrongKind(): void
    {
        $this->expectException(InvalidProduct::class);

        new MeasureSystem(MeasureUnit::L, MeasureUnit::L);
    }

    public function testTheConfiguredBaseDecidesWhatIsStored(): void
    {
        $grams = new MeasureSystem(MeasureUnit::G, MeasureUnit::ML);

        $this->assertEqualsWithDelta(1133.981, Quantity::of(2.5, MeasureUnit::LB, $grams)->baseValue(), 0.0005);
        $this->assertEqualsWithDelta(500.0, Quantity::of(0.5, MeasureUnit::L, $grams)->baseValue(), 0.0005);
        $this->assertEqualsWithDelta(1.134, Quantity::of(2.5, MeasureUnit::LB, $grams)->in(MeasureUnit::KG, $grams), 0.0005);
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
        yield 'pares con decimales' => [1.5, MeasureUnit::PAIR];
        yield 'docenas con decimales' => [1.5, MeasureUnit::DOZEN];
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
        $quantity = Quantity::fromStored(1.133981, 2.5, MeasureUnit::LB);

        $this->assertSame(1.133981, $quantity->baseValue());
        $this->assertSame(2.5, $quantity->originalValue());
        $this->assertSame(MeasureUnit::LB, $quantity->unit());
    }

    public function testUnitsAreFoundByTheirCode(): void
    {
        $this->assertSame(MeasureUnit::FL_OZ, MeasureUnit::tryFrom('fl_oz'));
        $this->assertNull(MeasureUnit::tryFrom('stone'));
    }
}
