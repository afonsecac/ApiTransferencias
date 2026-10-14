<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\DeleteProductImage;
use App\Market\Catalogo\Application\DescribeProductImage;
use App\Market\Catalogo\Application\ListProductImages;
use App\Market\Catalogo\Application\ReorderProductImages;
use App\Market\Catalogo\Application\SetProductImageReference;
use App\Market\Catalogo\Application\UploadProductImage;
use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\DeliveryBasis;
use App\Market\Catalogo\Domain\InvalidGalleryOrder;
use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Domain\MeasureKind;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Product;
use App\Market\Catalogo\Domain\ProductGalleryFull;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageNotFound;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductSpec;
use App\Market\Catalogo\Domain\ProductTexts;
use App\Market\Catalogo\Domain\Quantity;
use App\Market\Catalogo\Domain\Sku;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\UploadProductImage
 * @covers \App\Market\Catalogo\Application\ListProductImages
 * @covers \App\Market\Catalogo\Application\ReorderProductImages
 * @covers \App\Market\Catalogo\Application\SetProductImageReference
 * @covers \App\Market\Catalogo\Application\DescribeProductImage
 * @covers \App\Market\Catalogo\Application\DeleteProductImage
 */
final class ProductImageUseCasesTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryProductImageRepository $images;
    private FakeImageStore $store;
    private int $productId;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->images = new InMemoryProductImageRepository();
        $this->store = new FakeImageStore();
        $product = Product::create(new Sku('arr-001'), 1, new ProductSpec(
            ProductTexts::fromArray(['es' => ['name' => 'Arroz']]),
            Classification::of('Gift Cards', 'Retail', null),
            Quantity::of(1000, MeasureUnit::G),
            null,
            null,
            null,
            DeliveryBasis::of(MeasureKind::WEIGHT, MeasureUnit::G),
        ));
        $this->products->save($product);
        $this->productId = (int) $product->id();
    }

    private function upload(?FakeImageSettings $settings = null): UploadProductImage
    {
        return new UploadProductImage($this->products, $this->images, $this->store, $settings ?? new FakeImageSettings());
    }

    private function add(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            ($this->upload())($this->productId, '/tmp/x', "img-$i.jpg", 90_000);
        }
    }

    /**
     * @return list<int>
     */
    private function order(): array
    {
        return array_map(static fn (ProductImage $i): int => (int) $i->id(), $this->images->findByProduct($this->productId));
    }

    public function testTheFirstImageIsStoredAndBecomesTheReference(): void
    {
        $image = ($this->upload())($this->productId, '/tmp/x', 'arroz-frente.jpg', 90_000);

        $this->assertSame(['huella1'], $this->store->stored);
        $this->assertSame('huella1', $image->file());
        $this->assertSame('arroz-frente.jpg', $image->originalName());
        $this->assertSame(1, $image->position());
        $this->assertTrue($image->isReference());
        $this->assertSame([1200, 800], [$image->width(), $image->height()]);
    }

    public function testTheNextImagesGoAtTheEndAndAreNotTheReference(): void
    {
        $this->add(2);

        $images = $this->images->findByProduct($this->productId);
        $this->assertSame([1, 2], array_map(static fn (ProductImage $i) => $i->position(), $images));
        $this->assertSame([true, false], array_map(static fn (ProductImage $i) => $i->isReference(), $images));
    }

    public function testItFailsForAnUnknownProductWithoutStoringAnything(): void
    {
        try {
            ($this->upload())(999, '/tmp/x', 'a.jpg', 1000);
            $this->fail('Un producto inexistente no admite imágenes');
        } catch (ProductNotFound) {
            $this->assertSame([], $this->store->stored);
        }
    }

    public function testItRejectsAnImageOverTheConfiguredSize(): void
    {
        try {
            ($this->upload(new FakeImageSettings(maxBytes: 4_000_000)))($this->productId, '/tmp/x', 'grande.jpg', 4_000_001);
            $this->fail('Debió rechazar la imagen grande');
        } catch (InvalidProductImage) {
            $this->assertSame([], $this->store->stored);
            $this->assertSame([], $this->images->images);
        }
    }

    public function testItAcceptsAnImageExactlyAtTheLimit(): void
    {
        $image = ($this->upload(new FakeImageSettings(maxBytes: 4_000_000)))($this->productId, '/tmp/x', 'justa.jpg', 4_000_000);

        $this->assertNotNull($image->id());
    }

    public function testItDoesNotStoreAnythingWhenTheGalleryIsFull(): void
    {
        $settings = new FakeImageSettings(maxPerProduct: 2);
        ($this->upload($settings))($this->productId, '/tmp/x', 'a.jpg', 1000);
        ($this->upload($settings))($this->productId, '/tmp/x', 'b.jpg', 1000);

        try {
            ($this->upload($settings))($this->productId, '/tmp/x', 'c.jpg', 1000);
            $this->fail('La galería estaba llena');
        } catch (ProductGalleryFull) {
            $this->assertCount(2, $this->store->stored, 'No se guarda un archivo que no cabe.');
        }
    }

    public function testItSavesNothingWhenTheFileIsNotARealImage(): void
    {
        $this->store->rejects = true;

        try {
            ($this->upload())($this->productId, '/tmp/x', 'falso.jpg', 1000);
            $this->fail('Debió rechazar el archivo');
        } catch (InvalidProductImage) {
            $this->assertSame([], $this->images->images);
        }
    }

    public function testItRemovesTheStoredFilesIfSavingFails(): void
    {
        $this->images->failOnSave = true;

        try {
            ($this->upload())($this->productId, '/tmp/x', 'a.jpg', 1000);
            $this->fail('Debió fallar al guardar');
        } catch (\RuntimeException) {
            $this->assertSame(['huella1'], $this->store->deleted, 'No quedan archivos huérfanos.');
        }
    }

    public function testItListsTheGalleryInOrder(): void
    {
        $this->add(3);
        $this->images->findByProduct($this->productId);

        $listed = (new ListProductImages($this->products, $this->images))($this->productId);

        $this->assertSame([1, 2, 3], array_map(static fn (ProductImage $i) => $i->position(), $listed));
    }

    public function testListingFailsForAnUnknownProduct(): void
    {
        $this->expectException(ProductNotFound::class);

        (new ListProductImages($this->products, $this->images))(999);
    }

    public function testItReordersAndPersistsTheNewOrder(): void
    {
        $this->add(3);

        (new ReorderProductImages($this->products, $this->images, new FakeImageSettings()))($this->productId, [3, 1, 2]);

        $this->assertSame([3, 1, 2], $this->order());
    }

    public function testItRejectsAnOrderThatIsNotAPermutation(): void
    {
        $this->add(3);

        try {
            (new ReorderProductImages($this->products, $this->images, new FakeImageSettings()))($this->productId, [1, 2]);
            $this->fail('Debió rechazar el orden');
        } catch (InvalidGalleryOrder) {
            $this->assertSame([1, 2, 3], $this->order());
        }
    }

    public function testItChangesTheReference(): void
    {
        $this->add(3);

        (new SetProductImageReference($this->products, $this->images, new FakeImageSettings()))($this->productId, 2);

        $this->assertSame([false, true, false], array_map(static fn (ProductImage $i) => $i->isReference(), $this->images->findByProduct($this->productId)));
    }

    public function testItDescribesAnImage(): void
    {
        $this->add(1);

        $image = (new DescribeProductImage($this->products, $this->images))($this->productId, 1, 'frente', 'Arroz, frente', 'Rice, front');

        $this->assertSame(['frente', 'Arroz, frente', 'Rice, front'], [$image->kind(), $image->altEs(), $image->altEn()]);
    }

    public function testDescribingWithATooLongTextLeavesTheImageAsItWas(): void
    {
        $this->add(1);

        try {
            (new DescribeProductImage($this->products, $this->images))($this->productId, 1, str_repeat('x', 41), null, null);
            $this->fail('Debió rechazar el tipo');
        } catch (InvalidProductImage) {
            $this->assertNull($this->images->images[1]->kind());
        }
    }

    public function testAnImageOfAnotherProductIsNotFound(): void
    {
        $other = Product::create(new Sku('otro'), 1, $this->products->findById($this->productId)->spec());
        $this->products->save($other);
        $this->add(1);

        $this->expectException(ProductImageNotFound::class);

        (new DescribeProductImage($this->products, $this->images))((int) $other->id(), 1, null, null, null);
    }

    public function testItDeletesAnImageWithItsFilesAndPromotesTheNextReference(): void
    {
        $this->add(3);

        (new DeleteProductImage($this->products, $this->images, $this->store, new FakeImageSettings()))($this->productId, 1);

        $this->assertSame(['huella1'], $this->store->deleted);
        $this->assertSame([2, 3], $this->order());
        $remaining = $this->images->findByProduct($this->productId);
        $this->assertSame([1, 2], array_map(static fn (ProductImage $i) => $i->position(), $remaining));
        $this->assertTrue($remaining[0]->isReference());
    }

    public function testDeletingAnUnknownImageFails(): void
    {
        $this->expectException(ProductImageNotFound::class);

        (new DeleteProductImage($this->products, $this->images, $this->store, new FakeImageSettings()))($this->productId, 99);
    }
}
