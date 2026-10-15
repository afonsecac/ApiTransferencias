<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryCycle;
use App\Market\Catalogo\Domain\CategoryDepthExceeded;
use App\Market\Catalogo\Domain\CategoryTree;
use App\Market\Catalogo\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * COMR-174: árbol de hasta 3 niveles, sin ruta materializada: la profundidad y los ciclos se calculan sobre las categorías.
 *
 * @covers \App\Market\Catalogo\Domain\CategoryTree
 */
final class CategoryTreeTest extends TestCase
{
    /**
     * 1 Alimentos ─┬─ 2 Bebidas ─── 3 Refrescos
     *              └─ 4 Lácteos
     * 5 Aseo
     *
     * @return array<int, Category>
     */
    private function categories(): array
    {
        $make = static function (int $id, string $slug, ?int $parent): Category {
            $category = Category::create(new Slug($slug), $parent, ucfirst($slug), null);
            (new \ReflectionProperty(Category::class, 'id'))->setValue($category, $id);

            return $category;
        };

        return [
            1 => $make(1, 'alimentos', null),
            2 => $make(2, 'bebidas', 1),
            3 => $make(3, 'refrescos', 2),
            4 => $make(4, 'lacteos', 1),
            5 => $make(5, 'aseo', null),
        ];
    }

    private function tree(): CategoryTree
    {
        return new CategoryTree(array_values($this->categories()));
    }

    public function testTheDepthIsCountedFromTheRoot(): void
    {
        $tree = $this->tree();

        $this->assertSame(1, $tree->depthOf(1));
        $this->assertSame(2, $tree->depthOf(2));
        $this->assertSame(3, $tree->depthOf(3));
        $this->assertSame(1, $tree->depthOf(5));
    }

    public function testTheHeightOfASubtreeIsItsNumberOfLevels(): void
    {
        $tree = $this->tree();

        $this->assertSame(3, $tree->heightOf(1));
        $this->assertSame(2, $tree->heightOf(2));
        $this->assertSame(1, $tree->heightOf(3));
        $this->assertSame(1, $tree->heightOf(5));
    }

    public function testItAllowsANewChildWhileItStaysWithinThreeLevels(): void
    {
        $tree = $this->tree();

        $tree->assertCanAddChildTo(null);
        $tree->assertCanAddChildTo(1);
        $tree->assertCanAddChildTo(2);
        $this->addToAssertionCount(3);
    }

    public function testItRefusesAFourthLevel(): void
    {
        $this->expectException(CategoryDepthExceeded::class);

        $this->tree()->assertCanAddChildTo(3);
    }

    public function testItMovesAWholeSubtreeOnlyIfItStillFits(): void
    {
        $tree = $this->tree();

        $tree->assertCanMove(5, 1);   // una hoja bajo la raíz: 2 niveles
        $tree->assertCanMove(3, 4);   // una hoja bajo un hijo: 3 niveles
        $tree->assertCanMove(2, null); // un subárbol a la raíz
        $this->addToAssertionCount(3);

        $this->expectException(CategoryDepthExceeded::class);
        $tree->assertCanMove(1, 5);   // Alimentos (3 niveles) bajo Aseo: 4 niveles
    }

    public function testItRefusesToMoveACategoryUnderItselfOrItsDescendants(): void
    {
        $tree = $this->tree();

        foreach ([[1, 1], [1, 2], [1, 3], [2, 3]] as [$id, $newParent]) {
            try {
                $tree->assertCanMove($id, $newParent);
                $this->fail("Mover $id bajo $newParent crea un ciclo");
            } catch (CategoryCycle) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testItKnowsTheChildrenOfACategoryAndTheRoots(): void
    {
        $tree = $this->tree();

        $this->assertSame([1, 5], array_map(static fn (Category $c) => $c->id(), $tree->childrenOf(null)));
        $this->assertSame([2, 4], array_map(static fn (Category $c) => $c->id(), $tree->childrenOf(1)));
        $this->assertSame([], $tree->childrenOf(3));
    }

    public function testAnOrphanParentIsTreatedAsARoot(): void
    {
        $orphan = Category::create(new Slug('huerfana'), 99, 'Huérfana', null);
        (new \ReflectionProperty(Category::class, 'id'))->setValue($orphan, 8);

        $this->assertSame(1, (new CategoryTree([$orphan]))->depthOf(8));
    }

    public function testItListsAllTheDescendantsOfACategory(): void
    {
        $tree = $this->tree();

        $all = $tree->descendantsOf(1);
        sort($all);

        $this->assertSame([2, 3, 4], $all);
        $this->assertSame([3], $tree->descendantsOf(2));
        $this->assertSame([], $tree->descendantsOf(3));
        $this->assertSame([], $tree->descendantsOf(5));
        $this->assertSame([], $tree->descendantsOf(999));
    }
}
