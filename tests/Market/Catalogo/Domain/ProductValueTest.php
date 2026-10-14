<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\Dimensions;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\ProductValue;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Sku;
use PHPUnit\Framework\TestCase;

/**
 * Un producto lleva CONTENIDO (peso, volumen o unidades) o VALOR (fijo, o variable entre un mínimo y un máximo, como las
 * gift cards de DTOne): no hay un campo de tipo, lo dice la forma de sus datos. Con valor es digital: sin contenido,
 * dimensiones, entrega ni código de barras.
 *
 * @covers \App\Market\Catalogo\Domain\ProductValue
 * @covers \App\Market\Catalogo\Domain\ProductSpec
 */
final class ProductValueTest extends TestCase
{
    private function texts(): ProductTexts
    {
        return ProductTexts::fromArray(['es' => ['name' => 'Tarjeta Amazon'], 'en' => ['name' => 'Amazon card']]);
    }

    private function classification(): Classification
    {
        return Classification::of('Gift Cards', 'Retail');
    }

    private function fixed(): ProductValue
    {
        return ProductValue::fixed('USD', Amount::fromString('25'));
    }

    private function ranged(): ProductValue
    {
        return ProductValue::ranged('USD', Amount::fromString('5'), Amount::fromString('100'));
    }

    private function valueSpec(ProductValue $value, ?Classification $classification = null): ProductSpec
    {
        return new ProductSpec($this->texts(), $classification ?? $this->classification(), null, null, null, null, null, null, $value);
    }

    // ---- Valor ----

    public function testAFixedValueHasOneAmount(): void
    {
        $value = $this->fixed();

        $this->assertTrue($value->isFixed());
        $this->assertSame('25.00', $value->minAmount()->toString());
        $this->assertNull($value->maxAmount());
        $this->assertSame('USD', $value->currency());
    }

    public function testARangedValueHasAMinimumAndAMaximum(): void
    {
        $value = $this->ranged();

        $this->assertFalse($value->isFixed());
        $this->assertSame('5.00', $value->minAmount()->toString());
        $this->assertSame('100.00', $value->maxAmount()?->toString());
    }

    public function testTheMaximumMustBeGreaterThanTheMinimum(): void
    {
        foreach (['5', '4'] as $max) {
            try {
                ProductValue::ranged('USD', Amount::fromString('5'), Amount::fromString($max));
                $this->fail('Debió rechazar el rango');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheCurrencyIsThreeUppercaseLetters(): void
    {
        foreach (['us', 'USDX', 'U5D', ''] as $currency) {
            try {
                ProductValue::fixed($currency, Amount::fromString('10'));
                $this->fail("Debió rechazar la moneda $currency");
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('EUR', ProductValue::fixed(' eur ', Amount::fromString('10'))->currency());
    }

    // ---- Producto con valor (digital) ----

    public function testAProductWithValueIsDigital(): void
    {
        $spec = $this->valueSpec($this->fixed());

        $this->assertTrue($spec->isDigital());
        $this->assertNull($spec->content);
        $this->assertNull($spec->deliveryBasis);
        $this->assertNull($spec->totalWeightG());
        $this->assertNull($spec->totalVolumeMl());
        $this->assertSame('Retail', $spec->classification->subservice());
    }

    public function testAProductWithContentIsNotDigital(): void
    {
        $spec = new ProductSpec($this->texts(), $this->classification(), Quantity::of(500, MeasureUnit::G), null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G));

        $this->assertFalse($spec->isDigital());
        $this->assertNull($spec->value);
    }

    public function testAProductWithValueCannotCarryContentDimensionsDeliveryOrBarcode(): void
    {
        $value = $this->fixed();
        $classification = $this->classification();
        $attempts = [
            fn () => new ProductSpec($this->texts(), $classification, Quantity::of(500, MeasureUnit::G), null, null, null, null, null, $value),
            fn () => new ProductSpec($this->texts(), $classification, null, null, null, Dimensions::of(10, 10, 10), null, null, $value),
            fn () => new ProductSpec($this->texts(), $classification, null, null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G), null, $value),
            fn () => new ProductSpec($this->texts(), $classification, null, null, null, null, null, '8410000123456', $value),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Un producto con valor no lleva datos físicos');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAProductNeedsEitherContentOrValue(): void
    {
        foreach ([
            fn () => new ProductSpec($this->texts(), $this->classification(), null, null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G)),
            fn () => new ProductSpec($this->texts(), $this->classification(), Quantity::of(500, MeasureUnit::G), null, null, null, null),
            fn () => new ProductSpec($this->texts(), $this->classification(), null, null, null, null, null),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Un producto lleva contenido y entrega, o valor');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAProductWithValueSurvivesBeingStoredAndRebuilt(): void
    {
        foreach ([$this->fixed(), $this->ranged()] as $value) {
            $original = $this->valueSpec($value, Classification::of('Gift Cards', 'Retail', 'Supermarket'));

            $rebuilt = Product::create(new Sku('amazon-25'), 1, $original)->spec();

            $this->assertEquals($original, $rebuilt);
            $this->assertTrue($rebuilt->isDigital());
            $this->assertSame('Supermarket', $rebuilt->classification->area());
        }
    }

    public function testAProductWithValueHasNoTotalsStored(): void
    {
        $product = Product::create(new Sku('amazon-25'), 1, $this->valueSpec($this->fixed()));

        $this->assertNull($product->totalWeightG());
        $this->assertNull($product->totalVolumeMl());
    }

    public function testTheClassificationOfAProductCanChange(): void
    {
        $product = Product::create(new Sku('amazon-25'), 1, $this->valueSpec($this->fixed()));

        $product->update($this->valueSpec($this->fixed(), Classification::of('Gift Cards', 'Gaming')));

        $this->assertSame('Gaming', $product->spec()->classification->subservice());
    }
}
