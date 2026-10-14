<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\InvalidProductTransition;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Sku;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Domain\Product
 */
final class ProductTest extends TestCase
{
    private function spec(string $name = 'Refresco de cola', float $grams = 500): ProductSpec
    {
        return new ProductSpec(
            ProductTexts::fromArray(['es' => ['name' => $name]]),
            Classification::of('Gift Cards', 'Retail', 'Supermarket'),
            Quantity::of($grams, MeasureUnit::G),
            null,
            null,
            null,
            DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G),
        );
    }

    private function product(): Product
    {
        return Product::create(new Sku('cola-330'), 3, $this->spec());
    }

    public function testItIsBornAsADraftWithItsSkuAndSeller(): void
    {
        $product = $this->product();

        $this->assertSame(ProductStatus::DRAFT, $product->status());
        $this->assertSame('COLA-330', $product->sku()->value());
        $this->assertSame(3, $product->sellerId());
        $this->assertFalse($product->isPublished());
        $this->assertSame('Refresco de cola', $product->spec()->texts->forLanguage('es')['es']['name']);
    }

    public function testItIsPublishedAndWithdrawn(): void
    {
        $product = $this->product();

        $product->publish();
        $this->assertSame(ProductStatus::PUBLISHED, $product->status());
        $this->assertTrue($product->isPublished());

        $product->withdraw();
        $this->assertSame(ProductStatus::WITHDRAWN, $product->status());
        $this->assertFalse($product->isPublished());
    }

    public function testAWithdrawnProductCanBePublishedAgain(): void
    {
        $product = $this->product();
        $product->publish();
        $product->withdraw();

        $product->publish();

        $this->assertTrue($product->isPublished());
    }

    public function testChangingToTheSameStatusDoesNothing(): void
    {
        $product = $this->product();
        $product->publish();

        $product->publish();

        $this->assertTrue($product->isPublished());
    }

    public function testADraftCannotBeWithdrawn(): void
    {
        $this->expectException(InvalidProductTransition::class);

        $this->product()->withdraw();
    }

    public function testItUpdatesItsDataButNeverItsSkuNorItsSeller(): void
    {
        $product = $this->product();

        $product->update($this->spec('Refresco de cola light', 330));

        $this->assertSame('Refresco de cola light', $product->spec()->texts->forLanguage('es')['es']['name']);
        $this->assertEqualsWithDelta(330.0, $product->spec()->content->baseValue(), 0.001);
        $this->assertSame('COLA-330', $product->sku()->value());
        $this->assertSame(3, $product->sellerId());
        $this->assertFalse(method_exists($product, 'changeSku'));
        $this->assertFalse(method_exists($product, 'changeSeller'));
    }

    public function testTheStoredDataRebuildsTheSameSpec(): void
    {
        $original = new ProductSpec(
            ProductTexts::fromArray(['es' => ['name' => 'Cola', 'description' => 'Lata'], 'en' => ['name' => 'Cola soda']]),
            Classification::of('Gift Cards', 'Retail'),
            Quantity::of(12, MeasureUnit::UNIT),
            Quantity::of(11.64, MeasureUnit::OZ),
            null,
            null,
            DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::LB),
        );

        $rebuilt = Product::create(new Sku('x'), 1, $original)->spec();

        $this->assertEquals($original, $rebuilt);
    }
}
