<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\GetBrand;
use App\Market\Catalogo\Application\GetSeller;
use App\Market\Catalogo\Application\ListBrands;
use App\Market\Catalogo\Application\ListSellers;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\Seller;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * Cuántos productos tiene cada marca y cada vendedor, y cuántas marcas tiene un vendedor (COMR-280): se cuentan los productos
 * de cualquier estado, igual que los de las categorías.
 *
 * @covers \App\Market\Catalogo\Application\ListBrands
 * @covers \App\Market\Catalogo\Application\GetBrand
 * @covers \App\Market\Catalogo\Application\BrandDetails
 * @covers \App\Market\Catalogo\Application\ListSellers
 * @covers \App\Market\Catalogo\Application\GetSeller
 * @covers \App\Market\Catalogo\Application\SellerDetails
 */
final class BrandSellerProductCountsTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryBrandRepository $brands;
    private InMemorySellerRepository $sellers;
    private FakeSellerOrgUnits $orgUnits;
    private int $platform;
    private int $garajito;
    private int $casaYeya;
    private int $solDorado;
    private int $cola;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->brands = new InMemoryBrandRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->orgUnits = new FakeSellerOrgUnits();
        $this->orgUnits->add(1, 'Comremit', ['platform', 'seller']);
        $this->orgUnits->add(2, 'Garajito', ['seller']);

        $platform = Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma');
        $this->sellers->save($platform);
        $this->platform = (int) $platform->id();
        $garajito = Seller::create(2, 20, new Slug('garajito'), 'Garajito');
        $this->sellers->save($garajito);
        $this->garajito = (int) $garajito->id();

        $casaYeya = Brand::create($this->platform, new Slug('casa-yeya'), 'Casa Yeya');
        $this->brands->save($casaYeya);
        $this->casaYeya = (int) $casaYeya->id();
        $solDorado = Brand::create($this->platform, new Slug('sol-dorado'), 'Sol Dorado');
        $this->brands->save($solDorado);
        $this->solDorado = (int) $solDorado->id();
        $cola = Brand::create($this->garajito, new Slug('cola'), 'Cola');
        $this->brands->save($cola);
        $this->cola = (int) $cola->id();

        $categories = new InMemoryCategoryRepository();
        $category = Category::create(new Slug('alimentos'), null, 'Alimentos', null);
        $categories->save($category);
        $categoryId = (int) $category->id();

        $create = new CreateProduct($this->products, $this->sellers, $this->brands, $categories);
        // Plataforma: dos de Casa Yeya, uno sin marca · Garajito: uno de Cola
        $create('p1', $this->platform, $this->input($categoryId, $this->casaYeya));
        $create('p2', $this->platform, $this->input($categoryId, $this->casaYeya));
        $create('p3', $this->platform, $this->input($categoryId, null));
        $create('p4', $this->garajito, $this->input($categoryId, $this->cola));
        // Un producto retirado también cuenta
        $retired = $this->products->findBySku('P2');
        $retired?->publish();
        $retired?->withdraw();
    }

    private function input(int $categoryId, ?int $brandId): ProductSpecInput
    {
        return new ProductSpecInput(...[
            'texts' => ['es' => ['name' => 'Producto']],
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
            'categoryIds' => [$categoryId],
            'brandId' => $brandId,
        ]);
    }

    public function testEachBrandCountsItsProductsOfAnyStatus(): void
    {
        $counts = [];
        foreach ((new ListBrands($this->brands, $this->sellers, $this->products))(null, null, null) as $details) {
            $counts[(int) $details->brand->id()] = $details->productCount;
        }

        $this->assertSame(2, $counts[$this->casaYeya]);
        $this->assertSame(0, $counts[$this->solDorado]);
        $this->assertSame(1, $counts[$this->cola]);
    }

    public function testReadingOneBrandCarriesItsCount(): void
    {
        $details = (new GetBrand($this->brands, $this->sellers, $this->products))($this->casaYeya);

        $this->assertSame(2, $details->productCount);
    }

    public function testWithoutProductsTheBrandCountIsNotKnown(): void
    {
        $details = (new GetBrand($this->brands, $this->sellers))($this->casaYeya);

        $this->assertNull($details->productCount);
    }

    public function testEachSellerCountsItsProductsAndItsBrands(): void
    {
        $counts = [];
        foreach ((new ListSellers($this->sellers, $this->orgUnits, $this->products, $this->brands))(null, null) as $details) {
            $counts[(int) $details->seller->id()] = [$details->productCount, $details->brandCount];
        }

        $this->assertSame([3, 2], $counts[$this->platform], 'Tres productos (uno sin marca) y dos marcas.');
        $this->assertSame([1, 1], $counts[$this->garajito]);
    }

    public function testReadingOneSellerCarriesItsCounts(): void
    {
        $details = (new GetSeller($this->sellers, $this->orgUnits, $this->products, $this->brands))($this->platform);

        $this->assertSame(3, $details->productCount);
        $this->assertSame(2, $details->brandCount);
    }

    public function testWithoutProductsOrBrandsTheSellerCountsAreNotKnown(): void
    {
        $details = (new GetSeller($this->sellers, $this->orgUnits))($this->platform);

        $this->assertNull($details->productCount);
        $this->assertNull($details->brandCount);
    }
}
