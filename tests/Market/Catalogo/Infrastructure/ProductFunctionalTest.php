<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerRepository;
use App\Market\Catalogo\Infrastructure\Http\ChangeProductStatusDto;
use App\Market\Catalogo\Infrastructure\Http\CreateProductDto;
use App\Market\Catalogo\Infrastructure\Http\ProductController;
use App\Market\Catalogo\Infrastructure\Http\UpdateProductDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-273 contra Postgres real: persistencia del producto en la unidad base con su unidad original, y contrato HTTP.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\DoctrineProductRepository
 * @covers \App\Market\Catalogo\Infrastructure\Http\ProductController
 */
final class ProductFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): ProductController
    {
        return self::getContainer()->get(ProductController::class);
    }

    private function repository(): ProductRepository
    {
        return self::getContainer()->get(ProductRepository::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createDto(string $sku = 'COLA-330', array $overrides = []): CreateProductDto
    {
        return new CreateProductDto(...($overrides + [
            'sku' => $sku,
            'sellerId' => null,
            'texts' => ['es' => ['name' => 'Refresco de cola', 'description' => 'Lata'], 'en' => ['name' => 'Cola soda']],
            'contentValue' => 2.5,
            'contentUnit' => 'lb',
            'deliveryKind' => 'weight',
            'deliveryUnit' => 'kg',
        ]));
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    public function testItCreatesADraftForThePlatformSellerAndKeepsTheOriginalUnit(): void
    {
        $this->admin();

        $response = $this->controller()->create($this->createDto());
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('COLA-330', $data['sku']);
        $this->assertSame('draft', $data['status']);
        $this->assertTrue($data['seller']['isPlatform']);
        $this->assertSame(['value' => 2.5, 'unit' => 'lb', 'base' => 1133.981, 'baseUnit' => 'g', 'kind' => 'weight'], $data['content']);
        $this->assertSame(1133.981, $data['totalWeightG']);
        $this->assertNull($data['totalVolumeMl']);
        $this->assertSame(['kind' => 'weight', 'unit' => 'kg'], $data['deliveryBasis']);
    }

    public function testAProductSurvivesAPersistenceRoundTripWithItsOriginalUnits(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto('PACK-12', [
            'contentValue' => 12.0,
            'contentUnit' => 'unit',
            'unitWeightValue' => 11.64,
            'unitWeightUnit' => 'oz',
            'lengthMm' => 100.0,
            'widthMm' => 50.0,
            'heightMm' => 20.0,
            'deliveryKind' => 'volume',
            'deliveryUnit' => 'l',
            'area' => 'supermarket',
            'barcode' => '8410000123456',
        ])))['id'];
        $this->em->clear();

        $product = $this->repository()->findById($id);
        $spec = $product?->spec();

        $this->assertNotNull($spec);
        $this->assertSame(12.0, $spec->content->baseValue());
        $this->assertSame('oz', $spec->unitWeight?->unit()->value);
        $this->assertSame(11.64, $spec->unitWeight?->originalValue());
        $this->assertEqualsWithDelta(329.988, (float) $spec->unitWeight?->baseValue(), 0.001);
        $this->assertSame('supermarket', $spec->area?->value);
        $this->assertSame('8410000123456', $spec->barcode);
        $this->assertSame('es', array_key_first($spec->texts->all()));
        $this->assertEqualsWithDelta(3959.856, (float) $product?->totalWeightG(), 0.01);
        $this->assertEqualsWithDelta(12 * 100.0, (float) $product?->totalVolumeMl(), 0.01);
    }

    public function testASkuIsUniqueRegardlessOfCase(): void
    {
        $this->admin();
        $this->controller()->create($this->createDto('COLA-330'));

        $response = $this->controller()->create($this->createDto('cola-330'));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('PRODUCT_ALREADY_EXISTS', $this->body($response)['error']['code']);
    }

    public function testInvalidDataAnswersUnprocessableEntity(): void
    {
        $this->admin();

        foreach ([
            ['COLA 1', []],
            ['COLA-1', ['contentUnit' => 'stone']],
            ['COLA-2', ['contentValue' => 12.0, 'contentUnit' => 'unit']],
            ['COLA-3', ['texts' => ['en' => ['name' => 'Only English']]]],
        ] as [$sku, $overrides]) {
            $response = $this->controller()->create($this->createDto($sku, $overrides));

            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $sku);
            $this->assertSame('INVALID_PRODUCT', $this->body($response)['error']['code'], $sku);
        }
    }

    public function testAnUnknownOrInactiveSellerAnswersUnprocessableEntity(): void
    {
        $this->admin();

        $response = $this->controller()->create($this->createDto('COLA-9', ['sellerId' => 987654]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('PRODUCT_SELLER_INVALID', $this->body($response)['error']['code']);
    }

    public function testItUpdatesTheDataButNotTheSku(): void
    {
        $this->admin();
        $created = $this->body($this->controller()->create($this->createDto()));

        $response = $this->controller()->update($created['id'], new UpdateProductDto(
            texts: ['es' => ['name' => 'Cola light']],
            contentValue: 330.0,
            contentUnit: 'g',
            deliveryKind: 'weight',
            deliveryUnit: 'g',
        ));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('COLA-330', $data['sku']);
        $this->assertEquals(330.0, $data['content']['base']);
        $this->assertSame(['es'], array_keys($data['texts']));
        $this->assertSame($created['seller']['id'], $data['seller']['id']);
    }

    public function testThePublicationFlowAnswersConflictOnAnImpossibleTransition(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];

        $withdrawDraft = $this->controller()->changeStatus($id, new ChangeProductStatusDto('withdrawn'));
        $this->assertSame(Response::HTTP_CONFLICT, $withdrawDraft->getStatusCode());
        $this->assertSame('INVALID_PRODUCT_TRANSITION', $this->body($withdrawDraft)['error']['code']);

        $publish = $this->body($this->controller()->changeStatus($id, new ChangeProductStatusDto('published')));
        $this->assertSame('published', $publish['status']);
        $this->em->clear();
        $this->assertTrue($this->repository()->findById($id)?->isPublished());

        $this->assertSame('withdrawn', $this->body($this->controller()->changeStatus($id, new ChangeProductStatusDto('withdrawn')))['status']);
        $this->assertSame('published', $this->body($this->controller()->changeStatus($id, new ChangeProductStatusDto('published')))['status']);
    }

    public function testGoingBackToDraftIsRejected(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];

        $response = $this->controller()->changeStatus($id, new ChangeProductStatusDto('draft'));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testWithALanguageOnlyThoseTextsAreReturnedAndWithoutAllOfThem(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];

        $all = $this->body($this->controller()->get($id, new Request()));
        $english = $this->body($this->controller()->get($id, new Request(['lang' => 'en'])));
        $fallback = $this->body($this->controller()->get($id, new Request(['lang' => 'xx'])));

        $this->assertSame(['es', 'en'], array_keys($all['texts']));
        $this->assertSame(['en'], array_keys($english['texts']));
        $this->assertSame('Cola soda', $english['texts']['en']['name']);
        $this->assertSame(['es'], array_keys($fallback['texts']));
    }

    public function testAnUnknownProductAnswersNotFound(): void
    {
        $this->admin();

        $response = $this->controller()->get(987654, new Request());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('PRODUCT_NOT_FOUND', $this->body($response)['error']['code']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->changeStatus(987654, new ChangeProductStatusDto('published'))->getStatusCode());
    }

    public function testListFiltersByTextStatusAndSeller(): void
    {
        $this->admin();
        $cola = $this->body($this->controller()->create($this->createDto('COLA-330')))['id'];
        $this->controller()->create($this->createDto('AGUA-500', ['texts' => ['es' => ['name' => 'Agua mineral']]]));
        $this->controller()->changeStatus($cola, new ChangeProductStatusDto('published'));
        $platform = self::getContainer()->get(SellerRepository::class)->findPlatform();

        $skus = fn (Request $request) => array_column($this->body($this->controller()->list($request)), 'sku');

        $this->assertSame(['AGUA-500', 'COLA-330'], array_values(array_intersect(['AGUA-500', 'COLA-330'], $skus(new Request()))));
        $this->assertSame(['COLA-330'], array_values(array_intersect(['AGUA-500', 'COLA-330'], $skus(new Request(['q' => 'refresco'])))));
        $this->assertSame([], array_values(array_intersect(['AGUA-500', 'COLA-330'], $skus(new Request(['q' => 'description'])))), 'Las claves del JSON no cuentan: solo el sku y los nombres.');
        $this->assertContains('COLA-330', $skus(new Request(['status' => 'published'])));
        $this->assertNotContains('AGUA-500', $skus(new Request(['status' => 'published'])));
        $this->assertContains('AGUA-500', $skus(new Request(['seller' => (string) $platform?->id()])));
        $this->assertSame([], $skus(new Request(['seller' => '987654'])));
    }

    public function testTheDatabaseKeepsTheSkuUniqueAndTheSellerReferenced(): void
    {
        $this->admin();
        $this->controller()->create($this->createDto());
        $connection = $this->em->getConnection();

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $connection->executeStatement("INSERT INTO market_product (sku, seller_id, status, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, created_at, updated_at) SELECT 'COLA-330', seller_id, 'draft', '{}', 1, 1, 'g', 'weight', 'g', NOW(), NOW() FROM market_product LIMIT 1");
    }
}
