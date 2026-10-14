<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureUnit;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\ProductSpecInput
 */
final class ProductSpecInputTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private function input(array $overrides = []): ProductSpecInput
    {
        return new ProductSpecInput(...($overrides + [
            'texts' => ['es' => ['name' => 'Refresco de cola']],
            'service' => 'Gift Cards',
            'subservice' => 'Retail',
            'area' => null,
            'contentValue' => 2.5,
            'contentUnit' => 'lb',
            'unitWeightValue' => null,
            'unitWeightUnit' => null,
            'unitVolumeValue' => null,
            'unitVolumeUnit' => null,
            'lengthMm' => null,
            'widthMm' => null,
            'heightMm' => null,
            'deliveryKind' => 'weight',
            'deliveryUnit' => 'kg',
            'barcode' => null,
        ]));
    }

    /**
     * Un producto con valor (digital): sin contenido ni entrega.
     *
     * @param array<string, mixed> $overrides
     */
    private function withValue(array $overrides = []): ProductSpecInput
    {
        return $this->input($overrides + [
            'contentValue' => null,
            'contentUnit' => null,
            'deliveryKind' => null,
            'deliveryUnit' => null,
            'valueCurrency' => 'usd',
            'valueMin' => '25',
            'valueMax' => null,
        ]);
    }

    public function testItBuildsTheSpecKeepingTheOriginalUnit(): void
    {
        $spec = $this->input(['area' => 'Supermarket'])->toSpec();

        $this->assertSame(MeasureUnit::LB, $spec->content?->unit());
        $this->assertEqualsWithDelta(1133.981, (float) $spec->content?->baseValue(), 0.001);
        $this->assertSame(MeasureUnit::KG, $spec->deliveryBasis?->unit());
    }

    public function testItBuildsTheClassificationFromServiceSubserviceAndArea(): void
    {
        $classification = $this->input(['subservice' => 'Retail', 'area' => 'Supermarket'])->toSpec()->classification;

        $this->assertSame('Gift Cards', $classification->service());
        $this->assertSame('Retail', $classification->subservice());
        $this->assertSame('Supermarket', $classification->area());
    }

    public function testItBuildsAProductByUnitsWithItsReferences(): void
    {
        $spec = $this->input([
            'contentValue' => 12.0,
            'contentUnit' => 'unit',
            'unitWeightValue' => 330.0,
            'unitWeightUnit' => 'g',
        ])->toSpec();

        $this->assertEqualsWithDelta(3960.0, (float) $spec->totalWeightG(), 0.001);
    }

    public function testItBuildsTheDimensionsOnlyWhenAllThreeComeTogether(): void
    {
        $spec = $this->input(['lengthMm' => 100.0, 'widthMm' => 50.0, 'heightMm' => 20.0])->toSpec();
        $this->assertEqualsWithDelta(100.0, (float) $spec->totalVolumeMl(), 0.001);

        $this->expectException(InvalidProduct::class);
        $this->input(['lengthMm' => 100.0, 'widthMm' => 50.0])->toSpec();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalid(): iterable
    {
        yield 'unidad de contenido desconocida' => [['contentUnit' => 'stone']];
        yield 'unidad de tarifa desconocida' => [['deliveryUnit' => 'stone']];
        yield 'tipo de tarifa desconocido' => [['deliveryKind' => 'count']];
        yield 'servicio desconocido' => [['service' => 'Mobile']];
        yield 'Retail no es un servicio' => [['service' => 'Retail']];
        yield 'subservicio desconocido' => [['subservice' => 'Electronics']];
        yield 'área desconocida' => [['area' => 'Bakery']];
        yield 'Retail no es un área, es un subservicio' => [['area' => 'Retail']];
        yield 'Gift Cards no es un área, es un servicio' => [['area' => 'Gift Cards']];
        yield 'área de otro subservicio' => [['subservice' => 'Gaming', 'area' => 'Supermarket']];
        yield 'valor de referencia sin unidad' => [['contentValue' => 12.0, 'contentUnit' => 'unit', 'unitWeightValue' => 330.0, 'unitWeightUnit' => null]];
        yield 'unidad de referencia sin valor' => [['contentValue' => 12.0, 'contentUnit' => 'unit', 'unitWeightValue' => null, 'unitWeightUnit' => 'g']];
        yield 'sin contenido ni valor' => [['contentValue' => null, 'contentUnit' => null, 'deliveryKind' => null, 'deliveryUnit' => null]];
    }

    /**
     * @param array<string, mixed> $overrides
     * @dataProvider invalid
     */
    public function testItRejectsInconsistentData(array $overrides): void
    {
        $this->expectException(InvalidProduct::class);

        $this->input($overrides)->toSpec();
    }

    // ---- Valor (productos digitales como las gift cards, COMR-276) ----

    public function testItBuildsAFixedValue(): void
    {
        $spec = $this->withValue()->toSpec();

        $this->assertTrue($spec->isDigital());
        $this->assertTrue($spec->value?->isFixed());
        $this->assertSame('25.00', $spec->value?->minAmount()->toString());
        $this->assertSame('USD', $spec->value?->currency());
    }

    public function testItBuildsARangedValueWithAnAreaUnderRetail(): void
    {
        $spec = $this->withValue(['valueMin' => '5', 'valueMax' => '100.50', 'area' => 'Supermarket'])->toSpec();

        $this->assertFalse($spec->value?->isFixed());
        $this->assertSame('100.50', $spec->value?->maxAmount()?->toString());
        $this->assertSame('Supermarket', $spec->classification->area());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidValues(): iterable
    {
        yield 'sin importe' => [['valueMin' => null]];
        yield 'importe con coma' => [['valueMin' => '25,50']];
        yield 'importe con tres decimales' => [['valueMin' => '1.234']];
        yield 'sin moneda' => [['valueCurrency' => null]];
        yield 'moneda de dos letras' => [['valueCurrency' => 'us']];
        yield 'máximo menor que el mínimo' => [['valueMin' => '50', 'valueMax' => '10']];
        yield 'con contenido físico' => [['contentValue' => 500.0, 'contentUnit' => 'g']];
        yield 'con base de entrega' => [['deliveryKind' => 'weight', 'deliveryUnit' => 'g']];
        yield 'área de otro subservicio' => [['subservice' => 'Gaming', 'area' => 'Supermarket']];
    }

    /**
     * @param array<string, mixed> $overrides
     * @dataProvider invalidValues
     */
    public function testItRejectsAnInconsistentValue(array $overrides): void
    {
        $this->expectException(InvalidProduct::class);

        $this->withValue($overrides)->toSpec();
    }
}
