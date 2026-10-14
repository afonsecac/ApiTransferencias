<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\ChangeProductStatus;
use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\GetProduct;
use App\Market\Catalogo\Application\ListProducts;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Application\UpdateProduct;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\InvalidProductTransition;
use App\Market\Catalogo\Domain\ProductAlreadyExists;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductSellerInvalid;
use App\Market\Catalogo\Domain\ProductStatus;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\Slug;
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
    private Seller $platform;
    private Seller $garajito;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->platform = Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma');
        $this->garajito = Seller::create(2, 20, new Slug('garajito'), 'Garajito');
        $this->sellers->save($this->platform);
        $this->sellers->save($this->garajito);
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
        return new CreateProduct($this->products, $this->sellers);
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

        $updated = (new UpdateProduct($this->products, $this->sellers))($id, $this->input([
            'texts' => ['es' => ['name' => 'Refresco de cola light'], 'en' => ['name' => 'Light cola']],
            'contentValue' => 330.0,
        ]));

        $this->assertSame('Refresco de cola light', $updated->product->spec()->texts->all()['es']['name']);
        $this->assertEqualsWithDelta(330.0, $updated->product->spec()->content->baseValue(), 0.001);
        $this->assertSame('COLA-330', $updated->product->sku()->value());
        $this->assertSame($this->garajito->id(), $updated->product->sellerId());
    }

    public function testAnInvalidUpdateLeavesTheProductAsItWas(): void
    {
        $created = ($this->create())('cola-330', null, $this->input());
        $id = (int) $created->product->id();
        $saves = $this->products->saves;

        try {
            (new UpdateProduct($this->products, $this->sellers))($id, $this->input(['deliveryUnit' => 'stone']));
            $this->fail('Debió rechazar los datos');
        } catch (InvalidProduct) {
            $this->assertSame($saves, $this->products->saves);
            $this->assertEqualsWithDelta(500.0, $this->products->findById($id)?->spec()->content->baseValue(), 0.001);
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

        $updated = (new UpdateProduct($this->products, $this->sellers))($id, $this->valueInput(['subservice' => 'Gaming']));

        $this->assertSame('Gaming', $updated->product->spec()->classification->subservice());
        $this->assertSame('AMAZON-25', $updated->product->sku()->value());
    }

    public function testUpdatingAnUnknownProductFails(): void
    {
        $this->expectException(ProductNotFound::class);

        (new UpdateProduct($this->products, $this->sellers))(99, $this->input());
    }

    public function testItPublishesAndWithdraws(): void
    {
        $id = (int) ($this->create())('cola-330', null, $this->input())->product->id();
        $change = new ChangeProductStatus($this->products, $this->sellers);

        $this->assertSame(ProductStatus::PUBLISHED, $change($id, ProductStatus::PUBLISHED)->product->status());
        $this->assertSame(ProductStatus::WITHDRAWN, $change($id, ProductStatus::WITHDRAWN)->product->status());
        $this->assertSame(ProductStatus::PUBLISHED, $change($id, ProductStatus::PUBLISHED)->product->status());
    }

    public function testAProductCannotGoBackToDraftNorBeWithdrawnFromDraft(): void
    {
        $id = (int) ($this->create())('cola-330', null, $this->input())->product->id();
        $change = new ChangeProductStatus($this->products, $this->sellers);

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
        $get = new GetProduct($this->products, $this->sellers);

        $this->assertSame('Garajito', $get($id)->seller?->name());

        $this->expectException(ProductNotFound::class);
        $get(99);
    }

    public function testItListsProductsFilteredByTextStatusAndSeller(): void
    {
        $create = $this->create();
        $cola = (int) $create('cola-330', (int) $this->garajito->id(), $this->input())->product->id();
        $create('agua-500', null, $this->input(['texts' => ['es' => ['name' => 'Agua mineral']]]));
        (new ChangeProductStatus($this->products, $this->sellers))($cola, ProductStatus::PUBLISHED);
        $list = new ListProducts($this->products, $this->sellers);
        $skus = static fn (array $details) => array_map(static fn ($d) => $d->product->sku()->value(), $details);

        $this->assertSame(['AGUA-500', 'COLA-330'], $skus($list(null, null, null)));
        $this->assertSame(['COLA-330'], $skus($list('refresco', null, null)));
        $this->assertSame(['COLA-330'], $skus($list(null, ProductStatus::PUBLISHED, null)));
        $this->assertSame(['AGUA-500'], $skus($list(null, null, (int) $this->platform->id())));
    }
}
