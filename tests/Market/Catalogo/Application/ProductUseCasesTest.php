<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\ChangeProductStatus;
use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\GetProduct;
use App\Market\Catalogo\Application\ListProducts;
use App\Market\Catalogo\Application\ProductDetailsAssembler;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Application\SetProductRelevance;
use App\Market\Catalogo\Application\UpdateProduct;
use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\InvalidProductTransition;
use App\Market\Catalogo\Domain\ProductAlreadyExists;
use App\Market\Catalogo\Domain\ProductBrandInvalid;
use App\Market\Catalogo\Domain\ProductCategoryInvalid;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductSellerInvalid;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\ProductGallery;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\Seller;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\CreateProduct
 * @covers \App\Market\Catalogo\Application\UpdateProduct
 * @covers \App\Market\Catalogo\Application\ChangeProductStatus
 * @covers \App\Market\Catalogo\Application\GetProduct
 * @covers \App\Market\Catalogo\Application\ListProducts
 */
final class ProductUseCasesTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemorySellerRepository $sellers;
    private InMemoryBrandRepository $brands;
    private InMemoryCategoryRepository $categories;
    private InMemoryProductImageRepository $images;
    private Seller $platform;
    private Seller $garajito;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->images = new InMemoryProductImageRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->platform = Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma');
        $this->garajito = Seller::create(2, 20, new Slug('garajito'), 'Garajito');
        $this->sellers->save($this->platform);
        $this->sellers->save($this->garajito);
        $this->brands = new InMemoryBrandRepository();
        $this->categories = new InMemoryCategoryRepository();
    }

    private function assembler(): ProductDetailsAssembler
    {
        return new ProductDetailsAssembler($this->sellers, $this->brands, $this->categories, $this->images);
    }

    private function update(): UpdateProduct
    {
        return new UpdateProduct($this->products, $this->sellers, $this->brands, $this->categories);
    }

    private function brand(Seller $seller, string $slug = 'cola-premium'): Brand
    {
        $brand = Brand::create((int) $seller->id(), new Slug($slug), ucfirst($slug));
        $this->brands->save($brand);

        return $brand;
    }

    private function category(string $slug, ?int $parentId = null): Category
    {
        $category = Category::create(new Slug($slug), $parentId, ucfirst($slug), null);
        $this->categories->save($category);

        return $category;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function input(array $overrides = []): ProductSpecInput
    {
        return new ProductSpecInput(...($overrides + [
            'texts' => ['es' => ['name' => 'Refresco de cola']],
            'service' => 'Gift Cards',
            'subservice' => 'Retail',
            'area' => null,
            'contentValue' => 500.0,
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
        ]));
    }

    private function create(): CreateProduct
    {
        return new CreateProduct($this->products, $this->sellers, $this->brands, $this->categories);
    }

    public function testItCreatesADraftForTheGivenSeller(): void
    {
        $details = ($this->create())('cola-330', (int) $this->garajito->id(), $this->input());

        $this->assertSame(ProductStatus::DRAFT, $details->product->status());
        $this->assertSame('COLA-330', $details->product->sku()->value());
        $this->assertSame($this->garajito->id(), $details->product->sellerId());
        $this->assertSame('Garajito', $details->seller?->name());
    }

    public function testWithoutASellerItUsesThePlatformSeller(): void
    {
        $details = ($this->create())('cola-330', null, $this->input());

        $this->assertSame($this->platform->id(), $details->product->sellerId());
        $this->assertTrue($details->seller?->isPlatform());
    }

    public function testTheSellerMustExistAndBeActive(): void
    {
        foreach ([999, (int) $this->garajito->id()] as $i => $sellerId) {
            if ($i === 1) {
                $this->garajito->deactivate();
            }
            try {
                ($this->create())('cola-' . $i, $sellerId, $this->input());
                $this->fail('Debió rechazar el vendedor');
            } catch (ProductSellerInvalid) {
                $this->assertSame([], $this->products->products);
            }
        }
    }

    public function testTheSkuIsUniqueRegardlessOfCase(): void
    {
        ($this->create())('cola-330', null, $this->input());

        $this->expectException(ProductAlreadyExists::class);

        ($this->create())('COLA-330', (int) $this->garajito->id(), $this->input());
    }

    public function testAnInvalidSkuOrDataCreatesNothing(): void
    {
        foreach ([['sku' => 'COLA 330', 'input' => $this->input()], ['sku' => 'COLA-1', 'input' => $this->input(['contentUnit' => 'g', 'contentValue' => 0.0])]] as $case) {
            try {
                ($this->create())($case['sku'], null, $case['input']);
                $this->fail('Debió rechazar el producto');
            } catch (InvalidProduct) {
                $this->assertSame([], $this->products->products);
            }
        }
    }

    public function testItUpdatesTheDataButNotTheSkuNorTheSeller(): void
    {
        $created = ($this->create())('cola-330', (int) $this->garajito->id(), $this->input());
        $id = (int) $created->product->id();

        $updated = ($this->update())($id, $this->input([
            'texts' => ['es' => ['name' => 'Refresco de cola light'], 'en' => ['name' => 'Light cola']],
            'contentValue' => 330.0,
        ]));

        $this->assertSame('Refresco de cola light', $updated->product->spec()->texts->all()['es']['name']);
        $this->assertEqualsWithDelta(0.33, $updated->product->spec()->content->baseValue(), 0.0000001);
        $this->assertSame('COLA-330', $updated->product->sku()->value());
        $this->assertSame($this->garajito->id(), $updated->product->sellerId());
    }

    public function testAnInvalidUpdateLeavesTheProductAsItWas(): void
    {
        $created = ($this->create())('cola-330', null, $this->input());
        $id = (int) $created->product->id();
        $saves = $this->products->saves;

        try {
            ($this->update())($id, $this->input(['deliveryUnit' => 'stone']));
            $this->fail('Debió rechazar los datos');
        } catch (InvalidProduct) {
            $this->assertSame($saves, $this->products->saves);
            $this->assertEqualsWithDelta(0.5, $this->products->findById($id)?->spec()->content->baseValue(), 0.0000001);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function valueInput(array $overrides = []): ProductSpecInput
    {
        return $this->input($overrides + [
            'texts' => ['es' => ['name' => 'Tarjeta Amazon'], 'en' => ['name' => 'Amazon card']],
            'contentValue' => null,
            'contentUnit' => null,
            'deliveryKind' => null,
            'deliveryUnit' => null,
            'valueCurrency' => 'USD',
            'valueMin' => '25',
            'valueMax' => null,
        ]);
    }

    public function testItCreatesAProductWithValueAsADigitalProductForThePlatformSeller(): void
    {
        $details = ($this->create())('amazon-25', null, $this->valueInput());

        $this->assertTrue($details->product->spec()->isDigital());
        $this->assertSame(ProductStatus::DRAFT, $details->product->status());
        $this->assertTrue($details->seller?->isPlatform());
        $this->assertSame('Gift Cards', $details->product->spec()->classification->service());
    }

    public function testTheClassificationIsPartOfWhatCanBeUpdated(): void
    {
        $id = (int) ($this->create())('amazon-25', null, $this->valueInput())->product->id();

        $updated = ($this->update())($id, $this->valueInput(['subservice' => 'Gaming']));

        $this->assertSame('Gaming', $updated->product->spec()->classification->subservice());
        $this->assertSame('AMAZON-25', $updated->product->sku()->value());
    }

    public function testUpdatingAnUnknownProductFails(): void
    {
        $this->expectException(ProductNotFound::class);

        ($this->update())(99, $this->input());
    }

    public function testItPublishesAndWithdraws(): void
    {
        $category = $this->category('bebidas');
        $id = (int) ($this->create())('cola-330', null, $this->input(['categoryIds' => [(int) $category->id()]]))->product->id();
        $change = new ChangeProductStatus($this->products, $this->assembler(), new InMemoryProductPackages());

        $this->assertSame(ProductStatus::PUBLISHED, $change($id, ProductStatus::PUBLISHED)->product->status());
        $this->assertSame(ProductStatus::WITHDRAWN, $change($id, ProductStatus::WITHDRAWN)->product->status());
        $this->assertSame(ProductStatus::PUBLISHED, $change($id, ProductStatus::PUBLISHED)->product->status());
    }

    public function testAProductCannotGoBackToDraftNorBeWithdrawnFromDraft(): void
    {
        $category = $this->category('bebidas');
        $id = (int) ($this->create())('cola-330', null, $this->input(['categoryIds' => [(int) $category->id()]]))->product->id();
        $change = new ChangeProductStatus($this->products, $this->assembler(), new InMemoryProductPackages());

        try {
            $change($id, ProductStatus::WITHDRAWN);
            $this->fail('Un borrador no se retira');
        } catch (InvalidProductTransition) {
            $this->assertSame(ProductStatus::DRAFT, $this->products->findById($id)?->status());
        }

        $this->expectException(InvalidProductTransition::class);
        $change($id, ProductStatus::DRAFT);
    }

    public function testItGetsAProductWithItsSellerOrFailsWhenUnknown(): void
    {
        $id = (int) ($this->create())('cola-330', (int) $this->garajito->id(), $this->input())->product->id();
        $get = new GetProduct($this->products, $this->assembler());

        $this->assertSame('Garajito', $get($id)->seller?->name());

        $this->expectException(ProductNotFound::class);
        $get(99);
    }

    public function testItListsProductsFilteredByTextStatusAndSeller(): void
    {
        $create = $this->create();
        $category = $this->category('bebidas');
        $cola = (int) $create('cola-330', (int) $this->garajito->id(), $this->input(['categoryIds' => [(int) $category->id()]]))->product->id();
        $create('agua-500', null, $this->input(['texts' => ['es' => ['name' => 'Agua mineral']]]));
        (new ChangeProductStatus($this->products, $this->assembler(), new InMemoryProductPackages()))($cola, ProductStatus::PUBLISHED);
        $list = new ListProducts($this->products, $this->assembler(), $this->categories);
        $skus = static fn ($page) => array_map(static fn ($d) => $d->product->sku()->value(), $page->items);

        $this->assertSame(['AGUA-500', 'COLA-330'], $skus($list(null, null, null)));
        $this->assertSame(['COLA-330'], $skus($list('refresco', null, null)));
        $this->assertSame(['COLA-330'], $skus($list(null, ProductStatus::PUBLISHED, null)));
        $this->assertSame(['AGUA-500'], $skus($list(null, null, (int) $this->platform->id())));
    }

    public function testItCreatesAProductWithItsBrandAndCategories(): void
    {
        $brand = $this->brand($this->garajito);
        $food = $this->category('alimentos');
        $rice = $this->category('arroz', $food->id());

        $details = ($this->create())('arroz-1', (int) $this->garajito->id(), $this->input([
            'brandId' => (int) $brand->id(),
            'categoryIds' => [(int) $rice->id(), (int) $food->id()],
        ]));

        $this->assertSame($brand->id(), $details->product->brandId());
        $this->assertSame('Cola-premium', $details->brand?->name());
        $this->assertSame([(int) $food->id(), (int) $rice->id()], $details->product->categoryIds());
        $this->assertSame(['Alimentos', 'Arroz'], array_map(static fn (Category $c) => $c->nameEs(), $details->categories));
    }

    public function testTheBrandIsOptional(): void
    {
        $details = ($this->create())('tarjeta-1', null, $this->input());

        $this->assertNull($details->product->brandId());
        $this->assertNull($details->brand);
        $this->assertSame([], $details->categories);
    }

    public function testTheBrandMustBelongToTheProductsSeller(): void
    {
        $others = $this->brand($this->garajito);

        try {
            ($this->create())('cola-1', (int) $this->platform->id(), $this->input(['brandId' => (int) $others->id()]));
            $this->fail('Una marca de otro vendedor no debe aceptarse');
        } catch (ProductBrandInvalid $e) {
            $this->assertSame('PRODUCT_BRAND_SELLER_MISMATCH', $e->reason());
            $this->assertSame([], $this->products->products);
        }
    }

    public function testTheBrandMustExistAndBeActive(): void
    {
        $brand = $this->brand($this->garajito);
        $brand->deactivate();

        foreach ([999, (int) $brand->id()] as $brandId) {
            try {
                ($this->create())('cola-' . $brandId, (int) $this->garajito->id(), $this->input(['brandId' => $brandId]));
                $this->fail('Debió rechazar la marca');
            } catch (ProductBrandInvalid $e) {
                $this->assertSame('PRODUCT_BRAND_INVALID', $e->reason());
            }
        }
    }

    public function testTheCategoriesMustExistAndBeActive(): void
    {
        $inactive = $this->category('antigua');
        $inactive->deactivate();

        foreach ([999, (int) $inactive->id()] as $categoryId) {
            try {
                ($this->create())('cola-' . $categoryId, null, $this->input(['categoryIds' => [$categoryId]]));
                $this->fail('Debió rechazar la categoría');
            } catch (ProductCategoryInvalid) {
                $this->assertSame([], $this->products->products);
            }
        }
    }

    public function testItUpdatesTheBrandAndReplacesTheCategories(): void
    {
        $brand = $this->brand($this->platform);
        $food = $this->category('alimentos');
        $drinks = $this->category('bebidas');
        $id = (int) ($this->create())('cola-1', null, $this->input(['categoryIds' => [(int) $food->id()]]))->product->id();

        $updated = ($this->update())($id, $this->input(['brandId' => (int) $brand->id(), 'categoryIds' => [(int) $drinks->id()]]));

        $this->assertSame($brand->id(), $updated->product->brandId());
        $this->assertSame([(int) $drinks->id()], $updated->product->categoryIds());

        $cleared = ($this->update())($id, $this->input(['categoryIds' => [(int) $drinks->id()]]));
        $this->assertNull($cleared->product->brandId());
    }

    public function testAnEditKeepsAnAlreadyAssignedBrandAndCategoryEvenIfTheyWereDeactivatedSince(): void
    {
        $brand = $this->brand($this->platform);
        $food = $this->category('alimentos');
        $id = (int) ($this->create())('cola-1', null, $this->input(['brandId' => (int) $brand->id(), 'categoryIds' => [(int) $food->id()]]))->product->id();
        $brand->deactivate();
        $food->deactivate();

        $updated = ($this->update())($id, $this->input([
            'texts' => ['es' => ['name' => 'Otro nombre']],
            'brandId' => (int) $brand->id(),
            'categoryIds' => [(int) $food->id()],
        ]));

        $this->assertSame($brand->id(), $updated->product->brandId());
        $this->assertSame([(int) $food->id()], $updated->product->categoryIds());
    }

    public function testAProductCannotBePublishedWithoutACategory(): void
    {
        $id = (int) ($this->create())('cola-1', null, $this->input())->product->id();

        $this->expectException(InvalidProduct::class);

        (new ChangeProductStatus($this->products, $this->assembler(), new InMemoryProductPackages()))($id, ProductStatus::PUBLISHED);
    }

    public function testItListsByBrandAndByCategoryIncludingItsSubcategories(): void
    {
        $brand = $this->brand($this->platform);
        $food = $this->category('alimentos');
        $rice = $this->category('arroz', $food->id());
        $soap = $this->category('aseo');
        $create = $this->create();
        $create('arroz-1', null, $this->input(['brandId' => (int) $brand->id(), 'categoryIds' => [(int) $rice->id()]]));
        $create('aceite-1', null, $this->input(['categoryIds' => [(int) $food->id()]]));
        $create('jabon-1', null, $this->input(['categoryIds' => [(int) $soap->id()]]));
        $list = new ListProducts($this->products, $this->assembler(), $this->categories);
        $skus = static fn ($page) => array_map(static fn ($d) => $d->product->sku()->value(), $page->items);

        $this->assertSame(['ARROZ-1'], $skus($list(null, null, null, (int) $brand->id())));
        $this->assertSame(['ACEITE-1', 'ARROZ-1'], $skus($list(null, null, null, null, (int) $food->id())));
        $this->assertSame(['ACEITE-1'], $skus($list(null, null, null, null, (int) $food->id(), false)));
        $this->assertSame(['ARROZ-1'], $skus($list(null, null, null, null, (int) $rice->id())));
    }

    public function testItPagesTheListAndReportsTheTotal(): void
    {
        $create = $this->create();
        foreach (['a-1', 'b-2', 'c-3', 'd-4', 'e-5'] as $sku) {
            $create($sku, null, $this->input());
        }
        $list = new ListProducts($this->products, $this->assembler(), $this->categories);

        $second = $list(null, null, null, null, null, true, 2, 2);

        $this->assertSame(5, $second->total);
        $this->assertSame(2, $second->page);
        $this->assertSame(2, $second->perPage);
        $this->assertSame(['C-3', 'D-4'], array_map(static fn ($d) => $d->product->sku()->value(), $second->items));
    }

    public function testItSetsAndClearsTheRelevanceOfAProduct(): void
    {
        $id = (int) ($this->create())('cola-330', null, $this->input())->product->id();
        $set = new SetProductRelevance($this->products, $this->assembler());

        $details = $set($id, 2, '2026-10-12', '2026-10-31');

        $this->assertSame(2, $details->product->relevance()->level());
        $this->assertSame('2026-10-12', $details->product->relevance()->from()?->format('Y-m-d'));
        $this->assertSame('2026-10-31', $details->product->relevance()->until()?->format('Y-m-d'));

        $this->assertSame(0, $set($id, 0, null, null)->product->relevance()->level());
    }

    public function testRelevanceWithoutDatesHasNoLimit(): void
    {
        $id = (int) ($this->create())('cola-330', null, $this->input())->product->id();

        $details = (new SetProductRelevance($this->products, $this->assembler()))($id, 3, null, null);

        $this->assertNull($details->product->relevance()->from());
        $this->assertNull($details->product->relevance()->until());
    }

    /**
     * @dataProvider invalidRelevance
     */
    public function testAnInvalidRelevanceLeavesTheProductAsItWas(int $level, ?string $from, ?string $until): void
    {
        $id = (int) ($this->create())('cola-330', null, $this->input())->product->id();
        $set = new SetProductRelevance($this->products, $this->assembler());
        $set($id, 1, null, null);
        $saves = $this->products->saves;

        try {
            $set($id, $level, $from, $until);
            $this->fail('Debía rechazar la relevancia.');
        } catch (InvalidProduct) {
        }

        $this->assertSame(1, $this->products->findById($id)?->relevance()->level());
        $this->assertSame($saves, $this->products->saves);
    }

    /**
     * @return iterable<string, array{int, string|null, string|null}>
     */
    public static function invalidRelevance(): iterable
    {
        yield 'nivel 4' => [4, null, null];
        yield 'fin antes del inicio' => [2, '2026-10-31', '2026-10-12'];
        yield 'fecha mal escrita' => [2, '31/10/2026', null];
        yield 'fecha imposible' => [2, '2026-02-30', null];
    }

    public function testRelevanceOfAnUnknownProductFails(): void
    {
        $this->expectException(ProductNotFound::class);

        (new SetProductRelevance($this->products, $this->assembler()))(99, 1, null, null);
    }

    public function testItListsOnlyTheProductsWhoseRelevanceIsInForce(): void
    {
        $create = $this->create();
        $set = new SetProductRelevance($this->products, $this->assembler());
        $active = (int) $create('activo', null, $this->input())->product->id();
        $expired = (int) $create('vencido', null, $this->input())->product->id();
        $future = (int) $create('futuro', null, $this->input())->product->id();
        $create('normal', null, $this->input());
        $set($active, 2, '2026-10-01', '2026-10-31');
        $set($expired, 3, '2026-09-01', '2026-09-30');
        $set($future, 1, '2026-11-05', null);
        $list = new ListProducts($this->products, $this->assembler(), $this->categories);
        $skus = static fn ($page) => array_map(static fn ($d) => $d->product->sku()->value(), $page->items);

        $this->assertSame(['ACTIVO'], $skus($list(null, null, null, relevantOn: new \DateTimeImmutable('2026-10-15'))));
        $this->assertSame(['ACTIVO', 'FUTURO', 'NORMAL', 'VENCIDO'], $skus($list(null, null, null)));
    }

    private function addImages(int $productId, string ...$files): void
    {
        $gallery = ProductGallery::of([], 8);
        foreach ($files as $file) {
            $gallery->add(ProductImage::create($productId, $file, $file . '.png', 'image/png', 100, 10, 10));
        }
        $this->images->saveAll($gallery->images());
    }

    public function testEachProductOfTheListCarriesItsReferenceImageWhenItHasOne(): void
    {
        $create = $this->create();
        $withImages = (int) $create('con-fotos', null, $this->input())->product->id();
        $without = (int) $create('sin-fotos', null, $this->input())->product->id();
        $this->addImages($withImages, 'aaa', 'bbb');
        $list = new ListProducts($this->products, $this->assembler(), $this->categories);

        $items = [];
        foreach ($list(null, null, null)->items as $details) {
            $items[$details->product->sku()->value()] = $details->referenceImage;
        }

        $this->assertSame('aaa', $items['CON-FOTOS']?->file(), 'La primera imagen subida es la de referencia.');
        $this->assertTrue($items['CON-FOTOS']->isReference());
        $this->assertNull($items['SIN-FOTOS']);
        $this->assertNotSame($without, $withImages);
    }

    public function testReadingOneProductAlsoCarriesItsReferenceImage(): void
    {
        $id = (int) ($this->create())('con-fotos', null, $this->input())->product->id();
        $this->addImages($id, 'aaa');

        $details = (new GetProduct($this->products, $this->assembler()))($id);

        $this->assertSame('aaa', $details->referenceImage?->file());
    }
}
