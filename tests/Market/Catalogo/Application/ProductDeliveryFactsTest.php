<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\GetProductDeliveryFacts;
use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\Dimensions;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureSystem;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\ProductValue;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Sku;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\GetProductDeliveryFacts
 */
final class ProductDeliveryFactsTest extends TestCase
{
    private InMemoryProductRepository $products;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
    }

    private function facts(?InMemoryMeasureSettings $settings = null): GetProductDeliveryFacts
    {
        return new GetProductDeliveryFacts($this->products, $settings ?? new InMemoryMeasureSettings());
    }

    private function save(ProductSpec $spec): int
    {
        $product = Product::create(new Sku('p-' . random_int(1000, 9999)), 1, $spec);
        $this->products->save($product);

        return (int) $product->id();
    }

    private function spec(Quantity $content, ?Dimensions $dimensions, MeasureKind $kind, MeasureUnit $unit): ProductSpec
    {
        return new ProductSpec(
            ProductTexts::fromArray(['es' => ['name' => 'Producto de prueba']]),
            Classification::of('Gift Cards', 'Retail', 'Supermarket'),
            $content,
            null,
            null,
            $dimensions,
            DeliveryBasis::of($kind, $unit),
        );
    }

    public function testAProductByWeightGivesItsKilogramsAndItsBoxVolume(): void
    {
        $id = $this->save($this->spec(Quantity::of(2.4, MeasureUnit::KG), Dimensions::of(400, 400, 300), MeasureKind::WEIGHT, MeasureUnit::KG));

        $facts = ($this->facts())($id);

        $this->assertSame('weight', $facts?->basis);
        $this->assertSame(2.4, $facts?->weightKg);
        $this->assertSame(48000.0, $facts?->volumeCm3);
        $this->assertSame(6000, $facts?->volumetricDivisor);
        $this->assertSame('Producto de prueba', $facts?->name);
    }

    public function testAProductByVolumeGivesItsLiters(): void
    {
        $id = $this->save($this->spec(Quantity::of(1.5, MeasureUnit::L), null, MeasureKind::VOLUME, MeasureUnit::L));

        $facts = ($this->facts())($id);

        $this->assertSame('volume', $facts?->basis);
        $this->assertSame(1.5, $facts?->volumeLiters);
    }

    public function testTheQuantitiesAreConvertedToKilogramsAndLitersWhateverTheConfiguredBase(): void
    {
        $system = new MeasureSystem(MeasureUnit::G, MeasureUnit::ML);
        $id = $this->save($this->spec(Quantity::of(2500, MeasureUnit::G, $system), null, MeasureKind::WEIGHT, MeasureUnit::KG));
        $settings = new InMemoryMeasureSettings(MeasureUnit::G, MeasureUnit::ML, 5000);

        $facts = ($this->facts($settings))($id);

        $this->assertSame(2.5, $facts?->weightKg);
        $this->assertSame(5000, $facts?->volumetricDivisor);
    }

    public function testADigitalProductHasNoDeliveryBasis(): void
    {
        $digital = new ProductSpec(
            ProductTexts::fromArray(['es' => ['name' => 'Tarjeta']]),
            Classification::of('Gift Cards', 'Retail', 'Supermarket'),
            null,
            null,
            null,
            null,
            null,
            null,
            ProductValue::fixed('USD', Amount::fromString('25.00')),
        );
        $id = $this->save($digital);

        $facts = ($this->facts())($id);

        $this->assertNull($facts?->basis);
        $this->assertNull($facts?->weightKg);
    }

    public function testAnUnknownProductGivesNothing(): void
    {
        $this->assertNull(($this->facts())(999));
    }
}
