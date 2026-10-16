<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\GetProductStockFacts;
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
 * @covers \App\Market\Catalogo\Application\GetProductStockFacts
 */
final class ProductStockFactsTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemorySellerRepository $sellers;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->sellers->save(Seller::create(7, 77, new Slug('garajito'), 'Garajito'));
    }

    private function spec(bool $digital = false): ProductSpec
    {
        $classification = Classification::of('Gift Cards', 'Retail', 'Supermarket');
        $texts = ProductTexts::fromArray(['es' => ['name' => 'Arroz 1 kg']]);
        if ($digital) {
            return new ProductSpec($texts, $classification, null, null, null, null, null, null, ProductValue::fixed('USD', Amount::fromString('25.00')));
        }

        return new ProductSpec($texts, $classification, Quantity::of(1, MeasureUnit::KG), null, null, null, DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::KG));
    }

    private function save(ProductSpec $spec, int $sellerId = 1): int
    {
        $product = Product::create(new Sku('arroz-' . random_int(1000, 9999)), $sellerId, $spec);
        $this->products->save($product);

        return (int) $product->id();
    }

    public function testItGivesTheSellerAsOwnerWithItsCostCenter(): void
    {
        $id = $this->save($this->spec());

        $facts = (new GetProductStockFacts($this->products, $this->sellers))($id);

        $this->assertSame('Arroz 1 kg', $facts?->name);
        $this->assertSame('Garajito', $facts?->sellerName);
        $this->assertSame(77, $facts?->sellerCostCenterId);
        $this->assertTrue($facts?->physical);
    }

    public function testADigitalProductIsNotPhysical(): void
    {
        $id = $this->save($this->spec(true));

        $this->assertFalse((new GetProductStockFacts($this->products, $this->sellers))($id)?->physical);
    }

    public function testManyAndMissingProducts(): void
    {
        $a = $this->save($this->spec());
        $facts = new GetProductStockFacts($this->products, $this->sellers);

        $this->assertSame([$a], array_keys($facts->many([$a, 999])));
        $this->assertNull($facts(999));
    }
}
