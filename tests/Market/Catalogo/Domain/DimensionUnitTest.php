<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\DimensionUnit;
use PHPUnit\Framework\TestCase;

/**
 * COMR-281: las dimensiones se guardan siempre en milímetros; la unidad en que la persona las escribió se recuerda aparte,
 * solo para mostrárselas igual al editar.
 *
 * @covers \App\Market\Catalogo\Domain\DimensionUnit
 */
final class DimensionUnitTest extends TestCase
{
    /**
     * @return iterable<string, array{DimensionUnit, float, float}>
     */
    public static function conversions(): iterable
    {
        yield '10 mm son 10 mm' => [DimensionUnit::MM, 10.0, 10.0];
        yield '10 cm son 100 mm' => [DimensionUnit::CM, 10.0, 100.0];
        yield '1,5 m son 1500 mm' => [DimensionUnit::M, 1.5, 1500.0];
        yield '3,5 in son 88,9 mm' => [DimensionUnit::IN, 3.5, 88.9];
        yield '2 ft son 609,6 mm' => [DimensionUnit::FT, 2.0, 609.6];
    }

    /**
     * @dataProvider conversions
     */
    public function testItConvertsToMillimetersAndBackWithoutDrift(DimensionUnit $unit, float $value, float $mm): void
    {
        $this->assertEqualsWithDelta($mm, $unit->toMm($value), 0.0000001);
        $this->assertEqualsWithDelta($value, $unit->fromMm($mm), 0.0000001);
    }

    public function testItIsFoundByItsCode(): void
    {
        $this->assertSame(DimensionUnit::FT, DimensionUnit::tryFrom('ft'));
        $this->assertNull(DimensionUnit::tryFrom('yd'));
    }
}
