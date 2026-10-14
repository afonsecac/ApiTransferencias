<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\Dimensions;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\ProductArea;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Sku;
use PHPUnit\Framework\TestCase;

/**
 * COMR-273 / COMR-141 / COMR-104 / COMR-177: lo que describe a un producto y las reglas entre sus partes.
 *
 * @covers \App\Market\Catalogo\Domain\ProductSpec
 * @covers \App\Market\Catalogo\Domain\ProductTexts
 * @covers \App\Market\Catalogo\Domain\Sku
 * @covers \App\Market\Catalogo\Domain\Dimensions
 * @covers \App\Market\Catalogo\Domain\DeliveryBasis
 */
final class ProductSpecTest extends TestCase
{
    private function texts(): ProductTexts
    {
        return ProductTexts::fromArray(['es' => ['name' => 'Refresco de cola', 'description' => 'Lata de 330 ml'], 'en' => ['name' => 'Cola soda']]);
    }

    private function weightSpec(?Dimensions $dimensions = null, ?DeliveryBasis $basis = null): ProductSpec
    {
        return new ProductSpec(
            $this->texts(),
            Quantity::of(1.1, MeasureUnit::LB),
            null,
            null,
            $dimensions,
            $basis ?? DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::KG),
        );
    }

    // ---- SKU ----

    public function testASkuIsNormalisedToUppercase(): void
    {
        $this->assertSame('COLA-330.ML_1', (new Sku('  cola-330.ml_1 '))->value());
    }

    /**
     * @dataProvider invalidSkus
     */
    public function testAnInvalidSkuIsRejected(string $sku): void
    {
        $this->expectException(InvalidProduct::class);

        new Sku($sku);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSkus(): iterable
    {
        yield 'vacío' => [''];
        yield 'con espacios' => ['COLA 330'];
        yield 'empieza por guion' => ['-COLA'];
        yield 'caracteres raros' => ['COLA/330'];
        yield 'demasiado largo' => [str_repeat('A', 65)];
    }

    // ---- Textos por idioma ----

    public function testTheSpanishNameIsRequired(): void
    {
        $this->expectException(InvalidProduct::class);

        ProductTexts::fromArray(['en' => ['name' => 'Cola soda']]);
    }

    public function testAnUnsupportedLanguageOrABlankNameIsRejected(): void
    {
        foreach ([
            ['es' => ['name' => 'Cola'], 'fr' => ['name' => 'Cola']],
            ['es' => ['name' => 'Cola'], 'en' => ['name' => '  ']],
            ['es' => ['name' => str_repeat('N', 201)]],
            ['es' => ['name' => 'Cola', 'description' => str_repeat('D', 2001)]],
        ] as $texts) {
            try {
                ProductTexts::fromArray($texts);
                $this->fail('Debió rechazar los textos');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testWithoutALanguageItReturnsAllTheTexts(): void
    {
        $all = $this->texts()->forLanguage(null);

        $this->assertSame(['es', 'en'], array_keys($all));
        $this->assertSame('Cola soda', $all['en']['name']);
        $this->assertNull($all['en']['description']);
    }

    public function testWithALanguageItReturnsOnlyThatLanguage(): void
    {
        $this->assertSame(['en'], array_keys($this->texts()->forLanguage('en')));
    }

    public function testAMissingTranslationFallsBackToSpanish(): void
    {
        $onlySpanish = ProductTexts::fromArray(['es' => ['name' => 'Cola']]);

        $this->assertSame(['es'], array_keys($onlySpanish->forLanguage('en')));
        $this->assertSame(['es'], array_keys($onlySpanish->forLanguage('xx')));
    }

    // ---- Dimensiones y base de tarifa ----

    public function testTheVolumeComesFromTheThreeDimensions(): void
    {
        $this->assertEqualsWithDelta(100.0, Dimensions::of(100, 50, 20)->volumeCm3(), 0.0001);
    }

    public function testADimensionMustBePositive(): void
    {
        $this->expectException(InvalidProduct::class);

        Dimensions::of(100, 0, 20);
    }

    public function testTheDeliveryBasisIsWeightOrVolumeInAUnitOfThatKind(): void
    {
        $this->assertSame(MeasureUnit::LB, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::LB)->unit());

        foreach ([[MeasureKind::WEIGHT, MeasureUnit::L], [MeasureKind::VOLUME, MeasureUnit::KG], [MeasureKind::COUNT, MeasureUnit::UNIT]] as [$kind, $unit]) {
            try {
                DeliveryBasis::of($kind, $unit);
                $this->fail('Debió rechazar la base');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- Especificación ----

    public function testAProductByWeightKeepsTheOriginalUnitAndTheBaseValue(): void
    {
        $spec = $this->weightSpec();

        $this->assertEqualsWithDelta(1.1, $spec->content->originalValue(), 0.0001);
        $this->assertSame(MeasureUnit::LB, $spec->content->unit());
        $this->assertEqualsWithDelta(498.952, $spec->content->baseValue(), 0.001);
        $this->assertEqualsWithDelta(498.952, (float) $spec->totalWeightG(), 0.001);
        $this->assertNull($spec->totalVolumeMl());
    }

    public function testAProductByUnitsNeedsAtLeastTheWeightOrTheVolumeOfOneUnit(): void
    {
        $this->expectException(InvalidProduct::class);

        new ProductSpec($this->texts(), Quantity::of(12, MeasureUnit::UNIT), null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::KG));
    }

    public function testAProductByUnitsMultipliesTheReferenceByTheQuantity(): void
    {
        $spec = new ProductSpec(
            $this->texts(),
            Quantity::of(12, MeasureUnit::UNIT),
            Quantity::of(330, MeasureUnit::G),
            Quantity::of(0.33, MeasureUnit::L),
            null,
            DeliveryBasis::of(MeasureKind::VOLUME, MeasureUnit::L),
        );

        $this->assertEqualsWithDelta(3960.0, (float) $spec->totalWeightG(), 0.001);
        $this->assertEqualsWithDelta(3960.0, (float) $spec->totalVolumeMl(), 0.001);
    }

    public function testOnlyOneOfTheTwoReferencesIsEnough(): void
    {
        $spec = new ProductSpec($this->texts(), Quantity::of(6, MeasureUnit::UNIT), Quantity::of(200, MeasureUnit::G), null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G));

        $this->assertEqualsWithDelta(1200.0, (float) $spec->totalWeightG(), 0.001);
        $this->assertNull($spec->totalVolumeMl());
    }

    public function testTheReferencesOnlyApplyToProductsByUnits(): void
    {
        $this->expectException(InvalidProduct::class);

        new ProductSpec($this->texts(), Quantity::of(500, MeasureUnit::G), Quantity::of(500, MeasureUnit::G), null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G));
    }

    public function testTheReferencesMustBeOfTheRightKind(): void
    {
        $this->expectException(InvalidProduct::class);

        new ProductSpec($this->texts(), Quantity::of(6, MeasureUnit::UNIT), Quantity::of(1, MeasureUnit::L), null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G));
    }

    public function testTheDeliveryBasisMustBeComputableFromTheProduct(): void
    {
        // Pesa pero no se sabe cuánto ocupa: no se puede tarifar por volumen...
        try {
            $this->weightSpec(null, DeliveryBasis::of(MeasureKind::VOLUME, MeasureUnit::L));
            $this->fail('Debió rechazar la base por volumen');
        } catch (InvalidProduct) {
            $this->addToAssertionCount(1);
        }

        // ... salvo que lleve dimensiones: el volumen sale de ellas.
        $spec = $this->weightSpec(Dimensions::of(100, 50, 20), DeliveryBasis::of(MeasureKind::VOLUME, MeasureUnit::L));
        $this->assertEqualsWithDelta(100.0, (float) $spec->totalVolumeMl(), 0.001);
    }

    public function testTheDimensionsOfAProductByUnitsAreThoseOfOneUnit(): void
    {
        $spec = new ProductSpec(
            $this->texts(),
            Quantity::of(10, MeasureUnit::UNIT),
            Quantity::of(100, MeasureUnit::G),
            null,
            Dimensions::of(100, 50, 20),
            DeliveryBasis::of(MeasureKind::VOLUME, MeasureUnit::ML),
        );

        $this->assertEqualsWithDelta(1000.0, (float) $spec->totalVolumeMl(), 0.001);
    }

    public function testItValidatesTheBarcodeAndKeepsTheArea(): void
    {
        $spec = new ProductSpec($this->texts(), Quantity::of(500, MeasureUnit::G), null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G), ProductArea::SUPERMARKET, '8410000123456');

        $this->assertSame(ProductArea::SUPERMARKET, $spec->area);
        $this->assertSame('8410000123456', $spec->barcode);

        $this->expectException(InvalidProduct::class);
        new ProductSpec($this->texts(), Quantity::of(500, MeasureUnit::G), null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G), null, 'ABC');
    }
}
