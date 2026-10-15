<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\InvalidProductTransition;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Relevance;
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

    /** Un producto sin marca ni categorías. */
    private function bare(): Product
    {
        return Product::create(new Sku('cola-330'), 3, $this->spec());
    }

    /** Un producto con una categoría, que es lo mínimo para poder publicarse. */
    private function product(): Product
    {
        return Product::create(new Sku('cola-330'), 3, $this->spec(), null, [5]);
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
        $this->assertEqualsWithDelta(0.33, $product->spec()->content->baseValue(), 0.0000001);
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

    public function testItIsBornWithoutBrandNorCategories(): void
    {
        $product = $this->bare();

        $this->assertNull($product->brandId());
        $this->assertSame([], $product->categoryIds());
    }

    public function testItIsClassifiedWithABrandAndCategoriesWithoutDuplicates(): void
    {
        $product = $this->bare();

        $product->classify(7, [12, 5, 12]);

        $this->assertSame(7, $product->brandId());
        $this->assertSame([5, 12], $product->categoryIds());
    }

    public function testItCanBeBornClassified(): void
    {
        $product = Product::create(new Sku('cola-330'), 3, $this->spec(), 7, [5]);

        $this->assertSame(7, $product->brandId());
        $this->assertSame([5], $product->categoryIds());
    }

    public function testTheBrandCanBeRemovedButTheCategoriesAreReplaced(): void
    {
        $product = Product::create(new Sku('cola-330'), 3, $this->spec(), 7, [5, 6]);

        $product->classify(null, [6, 9]);

        $this->assertNull($product->brandId());
        $this->assertSame([6, 9], $product->categoryIds());
    }

    public function testACategoryIdMustBePositive(): void
    {
        $this->expectException(InvalidProduct::class);

        $this->bare()->classify(null, [0]);
    }

    public function testItCannotBePublishedWithoutAtLeastOneCategory(): void
    {
        $product = $this->bare();

        $this->expectException(InvalidProduct::class);

        $product->publish();
    }

    public function testItIsPublishedOnceItHasACategory(): void
    {
        $product = $this->bare();
        $product->classify(null, [5]);

        $product->publish();

        $this->assertTrue($product->isPublished());
    }

    public function testItIsBornWithoutRelevance(): void
    {
        $product = $this->product();

        $this->assertSame(0, $product->relevance()->level());
        $this->assertFalse($product->isRelevantOn(new \DateTimeImmutable('2026-10-15')));
    }

    public function testItIsRelevantOnlyWhileItsMarkIsInForce(): void
    {
        $product = $this->product();
        $product->setRelevance(Relevance::of(2, new \DateTimeImmutable('2026-10-12'), new \DateTimeImmutable('2026-10-31')));

        $this->assertSame(2, $product->relevance()->level());
        $this->assertTrue($product->isRelevantOn(new \DateTimeImmutable('2026-10-15')));
        $this->assertFalse($product->isRelevantOn(new \DateTimeImmutable('2026-11-02')));
    }

    public function testRelevanceCanBeRemoved(): void
    {
        $product = $this->product();
        $product->setRelevance(Relevance::of(3, null, null));
        $product->setRelevance(Relevance::none());

        $this->assertFalse($product->isRelevantOn(new \DateTimeImmutable('2026-10-15')));
    }
}
