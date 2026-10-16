<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Application\SuggestSku;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\Seller;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\SuggestSku
 */
final class SuggestSkuTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryCategoryRepository $categories;
    private InMemorySellerRepository $sellers;
    private InMemoryBrandRepository $brands;
    private int $alimentos;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->categories = new InMemoryCategoryRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->brands = new InMemoryBrandRepository();
        $this->sellers->save(Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma'));
        $category = Category::create(new Slug('alimentos'), null, 'Alimentos', null);
        $this->categories->save($category);
        $this->alimentos = (int) $category->id();
    }

    private function suggest(): SuggestSku
    {
        return new SuggestSku($this->products, $this->categories);
    }

    private function createProduct(string $sku, string $name): void
    {
        $input = new ProductSpecInput(...[
            'texts' => ['es' => ['name' => $name]],
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
        ]);
        (new CreateProduct($this->products, $this->sellers, $this->brands, $this->categories))($sku, null, $input);
    }

    public function testItProposesTheFirstConsecutiveWhenNothingUsesThePrefix(): void
    {
        $this->assertSame('ALI-ARROZ-001', ($this->suggest())('Arroz blanco', $this->alimentos)->value());
    }

    public function testItContinuesAfterTheSkusAlreadyStored(): void
    {
        $this->createProduct('ali-arroz-001', 'Arroz blanco');
        $this->createProduct('ALI-ARROZ-002', 'Arroz integral');
        $this->createProduct('ALI-FRIJOL-001', 'Frijol');

        $this->assertSame('ALI-ARROZ-003', ($this->suggest())('Arroz extra', $this->alimentos)->value());
        $this->assertSame('ALI-FRIJOL-002', ($this->suggest())('Frijol negro', $this->alimentos)->value());
    }

    public function testWithoutACategoryItUsesTheGenericPrefix(): void
    {
        $this->assertSame('GEN-ARROZ-001', ($this->suggest())('Arroz', null)->value());
    }

    public function testAnUnknownCategoryFails(): void
    {
        $this->expectException(CategoryNotFound::class);

        ($this->suggest())('Arroz', 999);
    }

    public function testAnEmptyNameFails(): void
    {
        $this->expectException(InvalidProduct::class);

        ($this->suggest())('   ', $this->alimentos);
    }
}
