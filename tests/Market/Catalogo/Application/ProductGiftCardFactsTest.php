<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\GetProductGiftCardFacts;
use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\ProductValue;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\Sku;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\GetProductGiftCardFacts
 */
final class ProductGiftCardFactsTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemorySellerRepository $sellers;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->sellers->save(Seller::create(7, 77, new Slug('garajito'), 'Garajito'));
    }

    private function save(?ProductValue $value): int
    {
        $classification = Classification::of('Gift Cards', 'Retail', 'Supermarket');
        $texts = ProductTexts::fromArray(['es' => ['name' => 'Tarjeta 25']]);
        $spec = $value !== null
            ? new ProductSpec($texts, $classification, null, null, null, null, null, null, $value)
            : new ProductSpec($texts, $classification, Quantity::of(1, MeasureUnit::KG), null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::KG));
        $product = Product::create(new Sku('tarjeta-' . random_int(1000, 9999)), 1, $spec);
        $this->products->save($product);

        return (int) $product->id();
    }

    private function facts(): GetProductGiftCardFacts
    {
        return new GetProductGiftCardFacts($this->products, $this->sellers);
    }

    public function testAFixedValueProductGivesItsValueAndItsSellersOrgUnit(): void
    {
        $facts = ($this->facts())($this->save(ProductValue::fixed('USD', Amount::fromString('25.00'))));

        $this->assertTrue($facts?->digital);
        $this->assertTrue($facts?->fixed);
        $this->assertSame(['USD', 2500, null], [$facts?->currency, $facts?->minMinor, $facts?->maxMinor]);
        $this->assertSame(7, $facts?->sellerOrgUnitId);
        $this->assertSame('Tarjeta 25', $facts?->name);
    }

    public function testARangedProductGivesItsRange(): void
    {
        $facts = ($this->facts())($this->save(ProductValue::ranged('USD', Amount::fromString('10.00'), Amount::fromString('100.00'))));

        $this->assertFalse($facts?->fixed);
        $this->assertSame([1000, 10000], [$facts?->minMinor, $facts?->maxMinor]);
    }

    public function testAPhysicalProductIsNotDigital(): void
    {
        $this->assertFalse(($this->facts())($this->save(null))?->digital);
    }

    public function testManyAndMissingProducts(): void
    {
        $id = $this->save(ProductValue::fixed('USD', Amount::fromString('25.00')));

        $this->assertSame([$id], array_keys(($this->facts())->many([$id, 999])));
        $this->assertNull(($this->facts())(999));
    }
}
