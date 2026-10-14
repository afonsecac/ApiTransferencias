<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\InvalidCategory;
use App\Market\Catalogo\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * COMR-70 / COMR-174: categorías en árbol (parent_id) con nombres en español e inglés. No se borran: se desactivan.
 *
 * @covers \App\Market\Catalogo\Domain\Category
 */
final class CategoryTest extends TestCase
{
    private function category(?int $parentId = null, string $nameEs = 'Bebidas', ?string $nameEn = 'Drinks'): Category
    {
        return Category::create(new Slug('bebidas'), $parentId, $nameEs, $nameEn);
    }

    public function testItIsCreatedActiveWithItsNamesInBothLanguages(): void
    {
        $category = $this->category(null, '  Bebidas  ', ' Drinks ');

        $this->assertSame('Bebidas', $category->nameEs());
        $this->assertSame('Drinks', $category->nameEn());
        $this->assertSame('bebidas', $category->slug()->value());
        $this->assertNull($category->parentId());
        $this->assertSame(0, $category->position());
        $this->assertTrue($category->isActive());
    }

    public function testTheEnglishNameIsOptional(): void
    {
        $this->assertNull($this->category(null, 'Bebidas', null)->nameEn());
        $this->assertNull($this->category(null, 'Bebidas', '   ')->nameEn());
    }

    public function testTheSpanishNameIsRequiredAndBothHaveALimit(): void
    {
        foreach ([['   ', 'Drinks'], [str_repeat('N', 121), 'Drinks'], ['Bebidas', str_repeat('N', 121)]] as [$es, $en]) {
            try {
                $this->category(null, $es, $en);
                $this->fail('Debió rechazar los nombres');
            } catch (InvalidCategory) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testItKeepsItsParent(): void
    {
        $this->assertSame(4, $this->category(4)->parentId());
    }

    public function testItIsUpdatedButKeepsItsSlugAndParent(): void
    {
        $category = $this->category(4);

        $category->update('Refrescos', 'Soft drinks', 3);

        $this->assertSame('Refrescos', $category->nameEs());
        $this->assertSame('Soft drinks', $category->nameEn());
        $this->assertSame(3, $category->position());
        $this->assertSame('bebidas', $category->slug()->value());
        $this->assertSame(4, $category->parentId());
    }

    public function testThePositionCannotBeNegative(): void
    {
        $this->expectException(InvalidCategory::class);

        $this->category()->update('Bebidas', 'Drinks', -1);
    }

    public function testItMovesToAnotherParentOrToTheRoot(): void
    {
        $category = $this->category(4);

        $category->moveTo(7);
        $this->assertSame(7, $category->parentId());

        $category->moveTo(null);
        $this->assertNull($category->parentId());
    }

    public function testItCannotBeItsOwnParent(): void
    {
        $category = Category::create(new Slug('x'), null, 'X', null);
        (new \ReflectionProperty(Category::class, 'id'))->setValue($category, 9);

        $this->expectException(InvalidCategory::class);

        $category->moveTo(9);
    }

    public function testItIsDeactivatedAndActivatedAgain(): void
    {
        $category = $this->category();

        $category->deactivate();
        $this->assertFalse($category->isActive());

        $category->activate();
        $this->assertTrue($category->isActive());
    }
}
