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
        $spec = $this->input(['area' => 'supermarket'])->toSpec();

        $this->assertSame(MeasureUnit::LB, $spec->content->unit());
        $this->assertEqualsWithDelta(1133.981, $spec->content->baseValue(), 0.001);
        $this->assertSame(ProductArea::SUPERMARKET, $spec->area);
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
        yield 'retail no es un área, es un subservicio' => [['area' => 'retail']];
        yield 'gift_cards no es un área, es un servicio' => [['area' => 'gift_cards']];
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

    // ---- Gift cards (COMR-276) ----

    /**
     * @param array<string, mixed> $overrides
     */
    private function giftCard(array $overrides = []): ProductSpecInput
    {
        return new ProductSpecInput(...($overrides + [
            'texts' => ['es' => ['name' => 'Tarjeta Amazon']],
            'contentValue' => null,
            'contentUnit' => null,
            'unitWeightValue' => null,
            'unitWeightUnit' => null,
            'unitVolumeValue' => null,
            'unitVolumeUnit' => null,
            'lengthMm' => null,
            'widthMm' => null,
            'heightMm' => null,
            'deliveryKind' => null,
            'deliveryUnit' => null,
            'area' => null,
            'barcode' => null,
            'giftCardCategory' => 'retail',
            'giftCardCurrency' => 'usd',
            'giftCardMin' => '25',
            'giftCardMax' => null,
        ]));
    }

    public function testItBuildsAFixedValueGiftCard(): void
    {
        $spec = $this->giftCard()->toSpec();

        $this->assertTrue($spec->isDigital());
        $this->assertTrue($spec->giftCard?->isFixed());
        $this->assertSame('25.00', $spec->giftCard?->minAmount()->toString());
        $this->assertSame('USD', $spec->giftCard?->currency());
    }

    public function testItBuildsARangedGiftCardWithItsAreaUnderRetail(): void
    {
        $spec = $this->giftCard(['giftCardMin' => '5', 'giftCardMax' => '100.50', 'area' => 'supermarket'])->toSpec();

        $this->assertFalse($spec->giftCard?->isFixed());
        $this->assertSame('100.50', $spec->giftCard?->maxAmount()?->toString());
        $this->assertSame(ProductArea::SUPERMARKET, $spec->area);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidGiftCards(): iterable
    {
        yield 'categoría desconocida' => [['giftCardCategory' => 'electronics']];
        yield 'sin importe' => [['giftCardMin' => null]];
        yield 'importe con coma' => [['giftCardMin' => '25,50']];
        yield 'sin moneda' => [['giftCardCurrency' => null]];
        yield 'máximo menor que el mínimo' => [['giftCardMin' => '50', 'giftCardMax' => '10']];
        yield 'con contenido físico' => [['contentValue' => 500.0, 'contentUnit' => 'g']];
        yield 'área de otro subservicio' => [['giftCardCategory' => 'gaming', 'area' => 'supermarket']];
    }

    /**
     * @param array<string, mixed> $overrides
     * @dataProvider invalidGiftCards
     */
    public function testItRejectsAnInconsistentGiftCard(array $overrides): void
    {
        $this->expectException(InvalidProduct::class);

        $this->giftCard($overrides)->toSpec();
    }

    public function testAPhysicalProductWithoutContentOrDeliveryIsRejected(): void
    {
        $this->expectException(InvalidProduct::class);

        $this->giftCard(['giftCardCategory' => null, 'giftCardCurrency' => null, 'giftCardMin' => null])->toSpec();
    }
}
