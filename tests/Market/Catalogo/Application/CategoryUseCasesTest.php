<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\CreateCategory;
use App\Market\Catalogo\Application\GetCategory;
use App\Market\Catalogo\Application\ListCategories;
use App\Market\Catalogo\Application\MoveCategory;
use App\Market\Catalogo\Application\SetCategoryActive;
use App\Market\Catalogo\Application\UpdateCategory;
use App\Market\Catalogo\Domain\CategoryAlreadyExists;
use App\Market\Catalogo\Domain\CategoryCycle;
use App\Market\Catalogo\Domain\CategoryDepthExceeded;
use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\InvalidCategory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\CreateCategory
 * @covers \App\Market\Catalogo\Application\UpdateCategory
 * @covers \App\Market\Catalogo\Application\MoveCategory
 * @covers \App\Market\Catalogo\Application\SetCategoryActive
 * @covers \App\Market\Catalogo\Application\GetCategory
 * @covers \App\Market\Catalogo\Application\ListCategories
 */
final class CategoryUseCasesTest extends TestCase
{
    private InMemoryCategoryRepository $categories;

    protected function setUp(): void
    {
        $this->categories = new InMemoryCategoryRepository();
    }

    private function create(): CreateCategory
    {
        return new CreateCategory($this->categories);
    }

    private function id(string $nameEs, ?int $parentId = null, ?string $nameEn = null): int
    {
        return (int) ($this->create())($parentId, $nameEs, $nameEn)->category->id();
    }

    public function testItCreatesARootCategoryDerivingTheSlugFromTheSpanishName(): void
    {
        $details = ($this->create())(null, 'Alimentos y bebidas', 'Food and drinks');

        $this->assertSame('alimentos-y-bebidas', $details->category->slug()->value());
        $this->assertSame('Food and drinks', $details->category->nameEn());
        $this->assertSame(1, $details->depth);
        $this->assertNull($details->category->parentId());
        $this->assertTrue($details->category->isActive());
    }

    public function testItAcceptsAnExplicitSlugAndPosition(): void
    {
        $details = ($this->create())(null, 'Bebidas', null, 'drinks', 4);

        $this->assertSame('drinks', $details->category->slug()->value());
        $this->assertSame(4, $details->category->position());
    }

    public function testItCreatesChildrenUpToThreeLevels(): void
    {
        $root = $this->id('Alimentos');
        $child = $this->id('Bebidas', $root);

        $grandchild = ($this->create())($child, 'Refrescos', null);

        $this->assertSame(3, $grandchild->depth);
    }

    public function testItRefusesAFourthLevelAndCreatesNothing(): void
    {
        $root = $this->id('Alimentos');
        $child = $this->id('Bebidas', $root);
        $grandchild = $this->id('Refrescos', $child);
        $count = count($this->categories->categories);

        try {
            ($this->create())($grandchild, 'Cola', null);
            $this->fail('Debió rechazar el cuarto nivel');
        } catch (CategoryDepthExceeded) {
            $this->assertCount($count, $this->categories->categories);
        }
    }

    public function testTheParentMustExist(): void
    {
        $this->expectException(CategoryNotFound::class);

        ($this->create())(99, 'Huérfana', null);
    }

    public function testTheSlugIsUnique(): void
    {
        ($this->create())(null, 'Bebidas', null, 'bebidas');

        $this->expectException(CategoryAlreadyExists::class);

        ($this->create())(null, 'Bebidas', null, 'bebidas');
    }

    public function testAnInvalidNameCreatesNothing(): void
    {
        try {
            ($this->create())(null, '  ', null);
            $this->fail('Debió rechazar el nombre');
        } catch (InvalidCategory|\App\Shared\Domain\InvalidSlug) {
            $this->assertSame([], $this->categories->categories);
        }
    }

    public function testItUpdatesTheNamesAndThePositionButNotTheSlugNorTheParent(): void
    {
        $root = $this->id('Alimentos');
        $id = $this->id('Bebidas', $root);

        $updated = (new UpdateCategory($this->categories))($id, 'Refrescos y bebidas', 'Drinks', 2);

        $this->assertSame('Refrescos y bebidas', $updated->category->nameEs());
        $this->assertSame('Drinks', $updated->category->nameEn());
        $this->assertSame(2, $updated->category->position());
        $this->assertSame('bebidas', $updated->category->slug()->value());
        $this->assertSame($root, $updated->category->parentId());
    }

    public function testUpdatingAnUnknownCategoryFails(): void
    {
        $this->expectException(CategoryNotFound::class);

        (new UpdateCategory($this->categories))(99, 'Nada', null, 0);
    }

    public function testItMovesACategoryToAnotherParentOrToTheRoot(): void
    {
        $food = $this->id('Alimentos');
        $care = $this->id('Aseo');
        $drinks = $this->id('Bebidas', $food);
        $move = new MoveCategory($this->categories);

        $this->assertSame($care, $move($drinks, $care)->category->parentId());
        $this->assertNull($move($drinks, null)->category->parentId());
        $this->assertSame(1, $move($drinks, null)->depth);
    }

    public function testMovingASubtreeKeepsItsDescendants(): void
    {
        $food = $this->id('Alimentos');
        $drinks = $this->id('Bebidas', $food);
        $soft = $this->id('Refrescos', $drinks);
        $care = $this->id('Aseo');

        (new MoveCategory($this->categories))($drinks, $care);

        $details = (new GetCategory($this->categories))($soft);
        $this->assertSame($drinks, $details->category->parentId());
        $this->assertSame(3, $details->depth);
    }

    public function testItRefusesToMoveUnderItselfOrItsDescendants(): void
    {
        $food = $this->id('Alimentos');
        $drinks = $this->id('Bebidas', $food);
        $move = new MoveCategory($this->categories);

        foreach ([$food, $drinks] as $newParent) {
            try {
                $move($food, $newParent);
                $this->fail('Mover bajo ella misma o una descendiente crea un ciclo');
            } catch (CategoryCycle) {
                $this->assertNull($this->categories->findById($food)?->parentId());
            }
        }
    }

    public function testItRefusesAMoveThatWouldPassThreeLevels(): void
    {
        $food = $this->id('Alimentos');
        $drinks = $this->id('Bebidas', $food);
        $this->id('Refrescos', $drinks);
        $care = $this->id('Aseo');

        try {
            (new MoveCategory($this->categories))($food, $care);
            $this->fail('Alimentos (3 niveles) bajo Aseo tendría 4');
        } catch (CategoryDepthExceeded) {
            $this->assertNull($this->categories->findById($food)?->parentId());
        }
    }

    public function testMovingToAnUnknownParentFails(): void
    {
        $id = $this->id('Bebidas');

        $this->expectException(CategoryNotFound::class);

        (new MoveCategory($this->categories))($id, 99);
    }

    public function testItDeactivatesAndActivatesACategoryWithoutTouchingItsChildren(): void
    {
        $food = $this->id('Alimentos');
        $drinks = $this->id('Bebidas', $food);
        $toggle = new SetCategoryActive($this->categories);

        $this->assertFalse($toggle($food, false)->category->isActive());
        $this->assertTrue($this->categories->findById($drinks)?->isActive());
        $this->assertTrue($toggle($food, true)->category->isActive());
    }

    public function testItGetsACategoryWithItsDepthAndChildrenOrFailsWhenUnknown(): void
    {
        $food = $this->id('Alimentos');
        $this->id('Bebidas', $food);
        $this->id('Lácteos', $food);

        $details = (new GetCategory($this->categories))($food);

        $this->assertSame(1, $details->depth);
        $this->assertSame(2, $details->childCount);

        $this->expectException(CategoryNotFound::class);
        (new GetCategory($this->categories))(99);
    }

    public function testItListsTheTreeDepthFirstByPositionAndName(): void
    {
        $food = $this->id('Alimentos');
        $care = ($this->create())(null, 'Aseo', null, null, 1)->category->id();
        $this->id('Lácteos', $food);
        $drinks = $this->id('Bebidas', $food);
        $this->id('Refrescos', $drinks);
        $names = static fn (array $details) => array_map(static fn ($d) => str_repeat('-', $d->depth - 1) . $d->category->nameEs(), $details);

        $list = (new ListCategories($this->categories))(null, null);

        $this->assertSame(['Alimentos', '-Bebidas', '--Refrescos', '-Lácteos', 'Aseo'], $names($list));
        $this->assertNotNull($care);
    }

    public function testItFiltersByStateAndByText(): void
    {
        $food = $this->id('Alimentos', null, 'Food');
        $this->id('Bebidas', $food, 'Drinks');
        (new SetCategoryActive($this->categories))($food, false);
        $list = new ListCategories($this->categories);
        $names = static fn (array $details) => array_map(static fn ($d) => $d->category->nameEs(), $details);

        $this->assertSame(['Bebidas'], $names($list(null, true)));
        $this->assertSame(['Alimentos'], $names($list(null, false)));
        $this->assertSame(['Bebidas'], $names($list('drinks', null)));
        $this->assertSame(['Alimentos'], $names($list('FOOD', null)));
    }

    public function testADerivedSlugThatIsTakenGetsTheNextNumber(): void
    {
        $first = ($this->create())(null, 'Bebidas', null);
        $second = ($this->create())(null, 'Bebidas', null);

        $this->assertSame('bebidas', $first->category->slug()->value());
        $this->assertSame('bebidas-2', $second->category->slug()->value());
    }

    public function testAnExplicitSlugThatIsTakenIsStillRejected(): void
    {
        ($this->create())(null, 'Bebidas', null, 'drinks');

        $this->expectException(\App\Market\Catalogo\Domain\CategoryAlreadyExists::class);
        ($this->create())(null, 'Otras bebidas', null, 'drinks');
    }
}
