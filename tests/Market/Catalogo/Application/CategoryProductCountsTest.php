<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\GetCategory;
use App\Market\Catalogo\Application\ListCategories;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * Cuántos productos tiene cada categoría (COMR-278): los suyos y los de ella y sus subcategorías, sin contar dos veces el
 * producto que está en la categoría y en una subcategoría.
 *
 * @covers \App\Market\Catalogo\Application\ListCategories
 * @covers \App\Market\Catalogo\Application\GetCategory
 * @covers \App\Market\Catalogo\Application\CategoryDetails
 * @covers \App\Market\Catalogo\Domain\CategoryProductCounts
 */
final class CategoryProductCountsTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryCategoryRepository $categories;
    private int $alimentos;
    private int $arroz;
    private int $aseo;
    private int $vacia;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->categories = new InMemoryCategoryRepository();
        $parent = Category::create(new Slug('alimentos'), null, 'Alimentos', null);
        $this->categories->save($parent);
        $this->alimentos = (int) $parent->id();
        $child = Category::create(new Slug('arroz'), $this->alimentos, 'Arroz', null);
        $this->categories->save($child);
        $this->arroz = (int) $child->id();
        $aseo = Category::create(new Slug('aseo'), null, 'Aseo', null);
        $this->categories->save($aseo);
        $this->aseo = (int) $aseo->id();
        $empty = Category::create(new Slug('vacia'), null, 'Vacía', null);
        $this->categories->save($empty);
        $this->vacia = (int) $empty->id();

        $sellers = new InMemorySellerRepository();
        $sellers->save(Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma'));
        $create = new CreateProduct($this->products, $sellers, new InMemoryBrandRepository(), $this->categories);
        // P1 en Arroz · P2 en Alimentos y en Arroz · P3 en Aseo · P4 en Alimentos
        $create('p1', null, $this->input([$this->arroz]));
        $create('p2', null, $this->input([$this->alimentos, $this->arroz]));
        $create('p3', null, $this->input([$this->aseo]));
        $create('p4', null, $this->input([$this->alimentos]));
    }

    /**
     * @param list<int> $categoryIds
     */
    private function input(array $categoryIds): ProductSpecInput
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
            'categoryIds' => $categoryIds,
        ]);
    }

    /**
     * @return array<int, array{int|null, int|null}> id → [propios, con subcategorías]
     */
    private function counts(): array
    {
        $out = [];
        foreach ((new ListCategories($this->categories, $this->products))(null, null) as $details) {
            $out[(int) $details->category->id()] = [$details->productCount, $details->totalProductCount];
        }

        return $out;
    }

    public function testEachCategoryCountsItsOwnProductsAndThoseOfItsSubcategoriesWithoutRepeating(): void
    {
        $counts = $this->counts();

        $this->assertSame([2, 3], $counts[$this->alimentos], 'P2 está en las dos y cuenta una vez: P1, P2 y P4.');
        $this->assertSame([2, 2], $counts[$this->arroz]);
        $this->assertSame([1, 1], $counts[$this->aseo]);
        $this->assertSame([0, 0], $counts[$this->vacia]);
    }

    public function testReadingOneCategoryCarriesTheSameCounts(): void
    {
        $details = (new GetCategory($this->categories, $this->products))($this->alimentos);

        $this->assertSame(2, $details->productCount);
        $this->assertSame(3, $details->totalProductCount);
    }

    public function testWithoutProductsTheCountsAreNotKnown(): void
    {
        $details = (new GetCategory($this->categories))($this->alimentos);

        $this->assertNull($details->productCount);
        $this->assertNull($details->totalProductCount);
    }
}
