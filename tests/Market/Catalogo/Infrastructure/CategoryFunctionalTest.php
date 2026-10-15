<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Infrastructure\Http\CategoryController;
use App\Market\Catalogo\Infrastructure\Http\CreateCategoryDto;
use App\Market\Catalogo\Infrastructure\Http\CreateProductDto;
use App\Market\Catalogo\Infrastructure\Http\ProductController;
use App\Market\Catalogo\Infrastructure\Http\MoveCategoryDto;
use App\Market\Catalogo\Infrastructure\Http\SetCategoryActiveDto;
use App\Market\Catalogo\Infrastructure\Http\UpdateCategoryDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-70 contra Postgres real: persistencia del árbol de categorías y contrato HTTP.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\DoctrineCategoryRepository
 * @covers \App\Market\Catalogo\Infrastructure\Http\CategoryController
 */
final class CategoryFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): CategoryController
    {
        return self::getContainer()->get(CategoryController::class);
    }

    private function repository(): CategoryRepository
    {
        return self::getContainer()->get(CategoryRepository::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    private function make(string $nameEs, ?int $parentId = null, ?string $nameEn = null, ?string $slug = null): int
    {
        $response = $this->controller()->create(new CreateCategoryDto($parentId, $nameEs, $nameEn, $slug));
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        return $this->body($response)['id'];
    }

    public function testItCreatesACategoryWithItsNamesInBothLanguagesAndItsLevel(): void
    {
        $this->admin();

        $response = $this->controller()->create(new CreateCategoryDto(null, 'Alimentos y bebidas', 'Food and drinks'));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('alimentos-y-bebidas', $data['slug']);
        $this->assertSame('Alimentos y bebidas', $data['nameEs']);
        $this->assertSame('Food and drinks', $data['nameEn']);
        $this->assertNull($data['parentId']);
        $this->assertSame(1, $data['depth']);
        $this->assertSame(0, $data['childCount']);
        $this->assertTrue($data['isActive']);
    }

    public function testATreeSurvivesAPersistenceRoundTrip(): void
    {
        $this->admin();
        $root = $this->make('Alimentos', null, 'Food', 'alimentos-x');
        $child = $this->make('Bebidas', $root);
        $leaf = $this->make('Refrescos', $child);
        $this->em->clear();

        $loaded = $this->repository()->findById($leaf);

        $this->assertSame($child, $loaded?->parentId());
        $this->assertSame('alimentos-x', $this->repository()->findById($root)?->slug()->value());
        $this->assertSame('Food', $this->repository()->findBySlug('alimentos-x')?->nameEn());
    }

    public function testAFourthLevelAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $leaf = $this->make('C', $this->make('B', $this->make('A')));

        $response = $this->controller()->create(new CreateCategoryDto($leaf, 'D'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('CATEGORY_DEPTH_EXCEEDED', $this->body($response)['error']['code']);
    }

    public function testAnUnknownParentAnswersNotFound(): void
    {
        $this->admin();

        $response = $this->controller()->create(new CreateCategoryDto(987654, 'Huérfana'));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('CATEGORY_NOT_FOUND', $this->body($response)['error']['code']);
    }

    public function testTheSlugIsUniqueAndInvalidDataAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $this->make('Bebidas');

        $duplicated = $this->controller()->create(new CreateCategoryDto(null, 'Otra', null, 'bebidas'));
        $this->assertSame(Response::HTTP_CONFLICT, $duplicated->getStatusCode());
        $this->assertSame('CATEGORY_ALREADY_EXISTS', $this->body($duplicated)['error']['code']);

        foreach ([['  ', null], ['Nombre', 'Mal Slug']] as [$name, $slug]) {
            $response = $this->controller()->create(new CreateCategoryDto(null, $name, null, $slug));
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
            $this->assertSame('INVALID_CATEGORY', $this->body($response)['error']['code']);
        }
    }

    public function testItUpdatesTheNamesAndThePositionButNotTheSlugNorTheParent(): void
    {
        $this->admin();
        $root = $this->make('Alimentos');
        $id = $this->make('Bebidas', $root);

        $response = $this->controller()->update($id, new UpdateCategoryDto('Refrescos y bebidas', 'Drinks', 2));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('Refrescos y bebidas', $data['nameEs']);
        $this->assertSame('Drinks', $data['nameEn']);
        $this->assertSame(2, $data['position']);
        $this->assertSame('bebidas', $data['slug']);
        $this->assertSame($root, $data['parentId']);
    }

    public function testItMovesACategoryWithItsSubtree(): void
    {
        $this->admin();
        $food = $this->make('Alimentos');
        $drinks = $this->make('Bebidas', $food);
        $soft = $this->make('Refrescos', $drinks);
        $care = $this->make('Aseo');

        $response = $this->controller()->move($drinks, new MoveCategoryDto($care));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->em->clear();

        $this->assertSame($care, $this->repository()->findById($drinks)?->parentId());
        $this->assertSame($drinks, $this->repository()->findById($soft)?->parentId());
        $this->assertSame(2, $this->body($this->controller()->get($drinks))['depth']);
        $this->assertSame(3, $this->body($this->controller()->get($soft))['depth']);
    }

    public function testItMovesACategoryToTheRoot(): void
    {
        $this->admin();
        $food = $this->make('Alimentos');
        $drinks = $this->make('Bebidas', $food);

        $data = $this->body($this->controller()->move($drinks, new MoveCategoryDto(null)));

        $this->assertNull($data['parentId']);
        $this->assertSame(1, $data['depth']);
    }

    public function testACycleOrATooDeepMoveAnswersUnprocessableEntityAndChangesNothing(): void
    {
        $this->admin();
        $food = $this->make('Alimentos');
        $drinks = $this->make('Bebidas', $food);
        $this->make('Refrescos', $drinks);
        $care = $this->make('Aseo');

        $cycle = $this->controller()->move($food, new MoveCategoryDto($drinks));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $cycle->getStatusCode());
        $this->assertSame('CATEGORY_CYCLE', $this->body($cycle)['error']['code']);

        $deep = $this->controller()->move($food, new MoveCategoryDto($care));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $deep->getStatusCode());
        $this->assertSame('CATEGORY_DEPTH_EXCEEDED', $this->body($deep)['error']['code']);

        $this->em->clear();
        $this->assertNull($this->repository()->findById($food)?->parentId());
    }

    public function testItDeactivatesACategoryWithoutTouchingItsChildren(): void
    {
        $this->admin();
        $food = $this->make('Alimentos');
        $drinks = $this->make('Bebidas', $food);

        $this->assertFalse($this->body($this->controller()->setActive($food, new SetCategoryActiveDto(false)))['isActive']);
        $this->em->clear();

        $this->assertFalse($this->repository()->findById($food)?->isActive());
        $this->assertTrue($this->repository()->findById($drinks)?->isActive());
        $this->assertTrue($this->body($this->controller()->setActive($food, new SetCategoryActiveDto(true)))['isActive']);
    }

    public function testAnUnknownCategoryAnswersNotFound(): void
    {
        $this->admin();

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->get(987654)->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->update(987654, new UpdateCategoryDto('X', null, 0))->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->move(987654, new MoveCategoryDto(null))->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->setActive(987654, new SetCategoryActiveDto(true))->getStatusCode());
    }

    public function testTheListIsInTreeOrderWithTheLevelOfEachCategoryAndFilters(): void
    {
        $this->admin();
        $food = $this->make('Alimentos zz', null, 'Food zz');
        $this->make('Lácteos zz', $food);
        $drinks = $this->make('Bebidas zz', $food);
        $this->make('Refrescos zz', $drinks);
        $this->controller()->setActive($drinks, new SetCategoryActiveDto(false));
        $ours = static fn (array $rows) => array_values(array_filter($rows, static fn (array $r) => str_ends_with($r['nameEs'], ' zz')));

        $all = $ours($this->body($this->controller()->list(new Request())));
        $this->assertSame(['Alimentos zz', 'Bebidas zz', 'Refrescos zz', 'Lácteos zz'], array_column($all, 'nameEs'));
        $this->assertSame([1, 2, 3, 2], array_column($all, 'depth'));

        $inactive = $ours($this->body($this->controller()->list(new Request(['active' => 'false']))));
        $this->assertSame(['Bebidas zz'], array_column($inactive, 'nameEs'));
        $found = $ours($this->body($this->controller()->list(new Request(['q' => 'food zz']))));
        $this->assertSame(['Alimentos zz'], array_column($found, 'nameEs'));
    }

    public function testTheDatabaseKeepsTheSlugUnique(): void
    {
        $this->admin();
        $this->make('Bebidas');

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->em->getConnection()->executeStatement("INSERT INTO market_category (slug, name_es, position, is_active, created_at, updated_at) VALUES ('bebidas', 'Dup', 0, TRUE, NOW(), NOW())");
    }

    public function testTheDatabaseKeepsTheParentReferenced(): void
    {
        $this->expectException(\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException::class);

        $this->em->getConnection()->executeStatement("INSERT INTO market_category (slug, parent_id, name_es, position, is_active, created_at, updated_at) VALUES ('huerfana', 987654, 'H', 0, TRUE, NOW(), NOW())");
    }

    public function testTheDatabaseDoesNotAllowACategoryToBeItsOwnParent(): void
    {
        $this->admin();
        $id = $this->make('Bebidas');

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $this->expectExceptionMessage('ck_market_category_not_own_parent');

        $this->em->getConnection()->executeStatement('UPDATE market_category SET parent_id = id WHERE id = :id', ['id' => $id]);
    }

    public function testTheListAndTheDetailCarryTheProductCountsOfEachCategoryAndItsSubcategories(): void
    {
        $this->admin();
        $food = $this->make('Alimentos Cuenta');
        $rice = $this->make('Arroz Cuenta', $food);
        $empty = $this->make('Vacía Cuenta');
        $products = self::getContainer()->get(ProductController::class);
        foreach ([['P1', [$rice]], ['P2', [$food, $rice]], ['P3', [$food]]] as [$sku, $categoryIds]) {
            $created = $products->create(new CreateProductDto(
                categoryIds: $categoryIds,
                sku: 'CNT-' . $sku,
                texts: ['es' => ['name' => 'Producto ' . $sku]],
                service: 'Gift Cards',
                subservice: 'Retail',
                contentValue: 1.0,
                contentUnit: 'kg',
                deliveryKind: 'weight',
                deliveryUnit: 'kg',
            ));
            $this->assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        }

        $byId = [];
        foreach ($this->body($this->controller()->list(new Request())) as $row) {
            $byId[$row['id']] = [$row['productCount'], $row['totalProductCount']];
        }
        $detail = $this->body($this->controller()->get($food));

        $this->assertSame([2, 3], $byId[$food]);
        $this->assertSame([2, 2], $byId[$rice]);
        $this->assertSame([0, 0], $byId[$empty]);
        $this->assertSame([2, 3], [$detail['productCount'], $detail['totalProductCount']]);
    }
}
