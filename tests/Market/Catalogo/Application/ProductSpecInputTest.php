<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\ProductArea;
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
        $values = $overrides + [
            'texts' => ['es' => ['name' => 'Refresco de cola']],
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
            'area' => null,
            'barcode' => null,
        ];

        return new ProductSpecInput(...$values);
    }

    public function testItBuildsTheSpecKeepingTheOriginalUnit(): void
    {
        $spec = $this->input(['area' => 'retail'])->toSpec();

        $this->assertSame(MeasureUnit::LB, $spec->content->unit());
        $this->assertEqualsWithDelta(1133.981, $spec->content->baseValue(), 0.001);
        $this->assertSame(ProductArea::RETAIL, $spec->area);
        $this->assertSame(MeasureUnit::KG, $spec->deliveryBasis->unit());
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
        yield 'área desconocida' => [['area' => 'electronics']];
        yield 'valor de referencia sin unidad' => [['contentValue' => 12.0, 'contentUnit' => 'unit', 'unitWeightValue' => 330.0, 'unitWeightUnit' => null]];
        yield 'unidad de referencia sin valor' => [['contentValue' => 12.0, 'contentUnit' => 'unit', 'unitWeightValue' => null, 'unitWeightUnit' => 'g']];
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
}
