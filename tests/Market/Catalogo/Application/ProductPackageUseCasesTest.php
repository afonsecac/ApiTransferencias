<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\ChangeProductStatus;
use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\GetProductPackage;
use App\Market\Catalogo\Application\ProductDetailsAssembler;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Application\PublishProductPackage;
use App\Market\Catalogo\Application\RetireProductPackage;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\Seller;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * Un producto suelto también es un paquete (COMR-275): publicarlo lo ofrece al comprador con un precio.
 *
 * @covers \App\Market\Catalogo\Application\PublishProductPackage
 * @covers \App\Market\Catalogo\Application\RetireProductPackage
 * @covers \App\Market\Catalogo\Application\GetProductPackage
 */
final class ProductPackageUseCasesTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryProductPackages $packages;
    private InMemoryCategoryRepository $categories;
    private InMemorySellerRepository $sellers;
    private InMemoryBrandRepository $brands;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->packages = new InMemoryProductPackages();
        $this->categories = new InMemoryCategoryRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->brands = new InMemoryBrandRepository();
        $this->sellers->save(Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma'));
        $this->categories->save(Category::create(new Slug('alimentos'), null, 'Alimentos', null));
    }

    private function assembler(): ProductDetailsAssembler
    {
        return new ProductDetailsAssembler($this->sellers, $this->brands, $this->categories);
    }

    /** Un producto con categoría, en el estado pedido. */
    private function product(ProductStatus $status = ProductStatus::PUBLISHED): int
    {
        $input = new ProductSpecInput(...[
            'texts' => ['es' => ['name' => 'Arroz blanco']],
            'service' => 'Gift Cards',
            'subservice' => 'Retail',
            'area' => 'Supermarket',
            'contentValue' => 1000.0,
            'contentUnit' => 'g',
            'unitWeightValue' => null,
            'unitWeightUnit' => null,
            'unitVolumeValue' => null,
            'unitVolumeUnit' => null,
            'lengthMm' => null,
            'widthMm' => null,
            'heightMm' => null,
            'deliveryKind' => 'weight',
            'deliveryUnit' => 'kg',
            'barcode' => null,
            'categoryIds' => [1],
        ]);
        $id = (int) (new CreateProduct($this->products, $this->sellers, $this->brands, $this->categories))('arr-001', null, $input)->product->id();
        if ($status === ProductStatus::PUBLISHED) {
            $this->changeStatus()($id, ProductStatus::PUBLISHED);
        }

        return $id;
    }

    private function changeStatus(): ChangeProductStatus
    {
        return new ChangeProductStatus($this->products, $this->assembler(), $this->packages);
    }

    private function publish(): PublishProductPackage
    {
        return new PublishProductPackage($this->products, $this->packages);
    }

    public function testItPublishesAPublishedProductAsAPackageWithItsPrice(): void
    {
        $id = $this->product();

        $offer = ($this->publish())($id, '12.50', 'usd');

        $this->assertSame(1250, $offer->price->minorUnits());
        $this->assertSame('USD', $offer->currency);
        $this->assertTrue($offer->isActive);
        $this->assertSame($offer->packageId, $this->packages->findFor($id)?->packageId);
    }

    public function testWithdrawingAProductAlsoRetiresItsOffer(): void
    {
        $id = $this->product();
        $offer = ($this->publish())($id, '12.50', 'USD');

        $this->changeStatus()($id, ProductStatus::WITHDRAWN);

        $after = $this->packages->findFor($id);
        $this->assertNotNull($after);
        $this->assertFalse($after->isActive, 'La oferta del producto retirado deja de ofrecerse.');
        $this->assertSame($offer->packageId, $after->packageId, 'El paquete no se borra.');
    }

    public function testWithdrawingAProductThatWasNeverOfferedCreatesNoOffer(): void
    {
        $id = $this->product();

        $this->changeStatus()($id, ProductStatus::WITHDRAWN);

        $this->assertNull($this->packages->findFor($id));
    }

    public function testPublishingAWithdrawnProductAgainDoesNotOfferItAgain(): void
    {
        $id = $this->product();
        ($this->publish())($id, '12.50', 'USD');
        $this->changeStatus()($id, ProductStatus::WITHDRAWN);

        $this->changeStatus()($id, ProductStatus::PUBLISHED);

        $this->assertFalse($this->packages->findFor($id)?->isActive, 'Volver a publicar el producto no reactiva la oferta: se ofrece de nuevo a propósito.');
    }

    public function testPublishingAgainChangesThePriceButKeepsThePackage(): void
    {
        $id = $this->product();
        $first = ($this->publish())($id, '12.50', 'USD');

        $second = ($this->publish())($id, '9.99', 'USD');

        $this->assertSame($first->packageId, $second->packageId);
        $this->assertSame(999, $second->price->minorUnits());
    }

    public function testADraftProductIsNotOfferedToBuyers(): void
    {
        $id = $this->product(ProductStatus::DRAFT);

        $this->expectException(InvalidProduct::class);
        try {
            ($this->publish())($id, '12.50', 'USD');
        } finally {
            $this->assertNull($this->packages->findFor($id));
        }
    }

    /**
     * @dataProvider invalidPrices
     */
    public function testAnInvalidPriceOrCurrencyPublishesNothing(string $price, string $currency): void
    {
        $id = $this->product();

        try {
            ($this->publish())($id, $price, $currency);
            $this->fail('Debía rechazar el precio.');
        } catch (InvalidProduct) {
        }

        $this->assertNull($this->packages->findFor($id));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPrices(): iterable
    {
        yield 'cero' => ['0', 'USD'];
        yield 'negativo' => ['-1', 'USD'];
        yield 'tres decimales' => ['1.999', 'USD'];
        yield 'texto' => ['barato', 'USD'];
        yield 'moneda corta' => ['5', 'US'];
    }

    public function testAnUnknownProductFails(): void
    {
        $this->expectException(ProductNotFound::class);

        ($this->publish())(99, '5', 'USD');
    }

    public function testItRetiresThePackageButKeepsItsRecord(): void
    {
        $id = $this->product();
        $offer = ($this->publish())($id, '12.50', 'USD');

        $retired = (new RetireProductPackage($this->products, $this->packages))($id);

        $this->assertTrue($retired);
        $this->assertFalse($this->packages->findFor($id)?->isActive);
        $this->assertSame($offer->packageId, $this->packages->findFor($id)?->packageId);
    }

    public function testRetiringAProductWithoutPackageDoesNothing(): void
    {
        $id = $this->product();

        $this->assertFalse((new RetireProductPackage($this->products, $this->packages))($id));
    }

    public function testRetiringAnUnknownProductFails(): void
    {
        $this->expectException(ProductNotFound::class);

        (new RetireProductPackage($this->products, $this->packages))(99);
    }

    public function testItReadsThePackageOfAProduct(): void
    {
        $id = $this->product();
        $get = new GetProductPackage($this->products, $this->packages);

        $this->assertNull($get($id));
        ($this->publish())($id, '12.50', 'USD');
        $this->assertSame(1250, $get($id)?->price->minorUnits());
    }

    public function testReadingThePackageOfAnUnknownProductFails(): void
    {
        $this->expectException(ProductNotFound::class);

        (new GetProductPackage($this->products, $this->packages))(99);
    }
}
