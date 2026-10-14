<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\Dimensions;
use App\Market\Catalogo\Domain\GiftCard;
use App\Market\Catalogo\Domain\GiftCardCategory;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductArea;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Sku;
use PHPUnit\Framework\TestCase;

/**
 * COMR-273 / COMR-276: las gift cards propias se modelan como las de DTOne. Son productos digitales (sin peso, contenido,
 * dimensiones ni entrega) de valor fijo o variable entre un mínimo y un máximo, clasificadas por categoría.
 *
 * @covers \App\Market\Catalogo\Domain\GiftCard
 * @covers \App\Market\Catalogo\Domain\GiftCardCategory
 * @covers \App\Market\Catalogo\Domain\ProductSpec
 */
final class GiftCardTest extends TestCase
{
    private function texts(): ProductTexts
    {
        return ProductTexts::fromArray(['es' => ['name' => 'Tarjeta Amazon'], 'en' => ['name' => 'Amazon card']]);
    }

    private function fixed(): GiftCard
    {
        return GiftCard::fixed(GiftCardCategory::RETAIL, 'USD', Amount::fromString('25'));
    }

    private function ranged(): GiftCard
    {
        return GiftCard::ranged(GiftCardCategory::GAMING, 'USD', Amount::fromString('5'), Amount::fromString('100'));
    }

    private function digitalSpec(GiftCard $giftCard): ProductSpec
    {
        return new ProductSpec($this->texts(), null, null, null, null, null, null, null, $giftCard);
    }

    // ---- Valor ----

    public function testAFixedValueGiftCardHasOneAmount(): void
    {
        $card = $this->fixed();

        $this->assertTrue($card->isFixed());
        $this->assertSame('25.00', $card->minAmount()->toString());
        $this->assertNull($card->maxAmount());
        $this->assertSame('USD', $card->currency());
        $this->assertSame(GiftCardCategory::RETAIL, $card->category());
    }

    public function testARangedGiftCardHasAMinimumAndAMaximum(): void
    {
        $card = $this->ranged();

        $this->assertFalse($card->isFixed());
        $this->assertSame('5.00', $card->minAmount()->toString());
        $this->assertSame('100.00', $card->maxAmount()?->toString());
    }

    public function testTheMaximumMustBeGreaterThanTheMinimum(): void
    {
        foreach (['5', '4'] as $max) {
            try {
                GiftCard::ranged(GiftCardCategory::GAMING, 'USD', Amount::fromString('5'), Amount::fromString($max));
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
                GiftCard::fixed(GiftCardCategory::RETAIL, $currency, Amount::fromString('10'));
                $this->fail("Debió rechazar la moneda $currency");
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('EUR', GiftCard::fixed(GiftCardCategory::RETAIL, ' eur ', Amount::fromString('10'))->currency());
    }

    public function testItUsesTheSixDTOneCategories(): void
    {
        $this->assertSame(
            ['retail', 'gaming', 'cash_cards', 'food', 'entertainment', 'travel_transport'],
            array_map(static fn (GiftCardCategory $c) => $c->value, GiftCardCategory::cases()),
        );
    }

    // ---- Categoría (subservicio) y área ----

    public function testRetailIsASubserviceAndSupermarketIsAnAreaUnderIt(): void
    {
        $this->assertSame(['supermarket'], array_map(static fn (ProductArea $a) => $a->value, ProductArea::cases()));
        $this->assertSame(GiftCardCategory::RETAIL, ProductArea::SUPERMARKET->subservice());
    }

    public function testAGiftCardCanHaveTheAreaOfItsSubservice(): void
    {
        $spec = new ProductSpec($this->texts(), null, null, null, null, null, ProductArea::SUPERMARKET, null, $this->fixed());

        $this->assertSame(ProductArea::SUPERMARKET, $spec->area);
    }

    public function testAnAreaOnlyExistsUnderItsOwnSubservice(): void
    {
        $this->expectException(InvalidProduct::class);

        new ProductSpec($this->texts(), null, null, null, null, null, ProductArea::SUPERMARKET, null, $this->ranged());
    }

    // ---- Producto digital ----

    public function testAGiftCardIsADigitalProductWithoutPhysicalData(): void
    {
        $spec = $this->digitalSpec($this->fixed());

        $this->assertTrue($spec->isDigital());
        $this->assertNull($spec->content);
        $this->assertNull($spec->deliveryBasis);
        $this->assertNull($spec->totalWeightG());
        $this->assertNull($spec->totalVolumeMl());
        $this->assertSame(GiftCardCategory::RETAIL, $spec->giftCard?->category());
    }

    public function testAPhysicalProductIsNotDigital(): void
    {
        $spec = new ProductSpec($this->texts(), Quantity::of(500, MeasureUnit::G), null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G));

        $this->assertFalse($spec->isDigital());
        $this->assertNull($spec->giftCard);
    }

    public function testAGiftCardCannotCarryWeightContentDimensionsDeliveryOrBarcode(): void
    {
        $card = $this->fixed();
        $attempts = [
            fn () => new ProductSpec($this->texts(), Quantity::of(500, MeasureUnit::G), null, null, null, null, null, null, $card),
            fn () => new ProductSpec($this->texts(), null, null, null, Dimensions::of(10, 10, 10), null, null, null, $card),
            fn () => new ProductSpec($this->texts(), null, null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G), null, null, $card),
            fn () => new ProductSpec($this->texts(), null, null, null, null, null, null, '8410000123456', $card),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Una gift card no lleva datos físicos');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAPhysicalProductStillNeedsItsContentAndDeliveryBasis(): void
    {
        foreach ([
            fn () => new ProductSpec($this->texts(), null, null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G)),
            fn () => new ProductSpec($this->texts(), Quantity::of(500, MeasureUnit::G), null, null, null, null),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Un producto físico necesita contenido y base de tarifa');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testADigitalProductSurvivesBeingStoredAndRebuilt(): void
    {
        foreach ([$this->fixed(), $this->ranged()] as $card) {
            $original = $this->digitalSpec($card);

            $rebuilt = Product::create(new Sku('amazon-25'), 1, $original)->spec();

            $this->assertEquals($original, $rebuilt);
            $this->assertTrue($rebuilt->isDigital());
        }
    }

    public function testADigitalProductHasNoTotalsStored(): void
    {
        $product = Product::create(new Sku('amazon-25'), 1, $this->digitalSpec($this->fixed()));

        $this->assertNull($product->totalWeightG());
        $this->assertNull($product->totalVolumeMl());
    }
}
