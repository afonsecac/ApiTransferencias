<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\ProductGallery;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageRepository;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\SellerRepository;
use App\Market\Catalogo\Domain\Slug;
use App\Market\Catalogo\Infrastructure\Http\ChangeProductStatusDto;
use App\Market\Catalogo\Infrastructure\Http\CreateProductDto;
use App\Market\Catalogo\Infrastructure\Http\ProductController;
use App\Market\Catalogo\Infrastructure\Http\SetProductRelevanceDto;
use App\Market\Catalogo\Infrastructure\Http\UpdateProductDto;
use App\Market\Organizacion\Infrastructure\Http\CostCenterDto;
use App\Market\Organizacion\Infrastructure\Http\CreateOrgUnitDto;
use App\Market\Organizacion\Infrastructure\Http\OrgUnitController;
use App\Market\Catalogo\Infrastructure\Http\SellerController;
use App\Market\Catalogo\Infrastructure\Http\CreateSellerDto;
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
            'categoryIds' => [$this->defaultCategoryId()],
            'sku' => $sku,
            'sellerId' => null,
            'texts' => ['es' => ['name' => 'Refresco de cola', 'description' => 'Lata'], 'en' => ['name' => 'Cola soda']],
            'service' => 'Gift Cards',
            'subservice' => 'Retail',
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

    private ?int $defaultCategory = null;

    /** La categoría con la que se crean los productos de estas pruebas (publicar exige al menos una). */
    private function defaultCategoryId(): int
    {
        return $this->defaultCategory ??= $this->categoryId('General');
    }

    private function categoryId(string $name, ?int $parentId = null): int
    {
        $category = Category::create(new Slug(strtolower($name)), $parentId, $name, null);
        self::getContainer()->get(CategoryRepository::class)->save($category);

        return (int) $category->id();
    }

    /** Un vendedor que no es el de plataforma (con su unidad organizativa y su centro de costo). */
    private function otherSellerId(): int
    {
        $unit = $this->body(self::getContainer()->get(OrgUnitController::class)->create(new CreateOrgUnitDto(
            countryCode: 'PA',
            name: 'La Jabalina SA',
            legalName: 'La Jabalina SA',
            taxId: 'JAB-1',
            address: 'Calle 50, Ciudad de Panamá',
            billingEmail: 'billing@jabalina.example',
            types: ['seller'],
            fiscalData: ['ruc' => 'JAB-1', 'dv' => '12'],
            costCenters: [new CostCenterDto('PRINCIPAL', 'Principal', true)],
        )));

        return (int) $this->body(self::getContainer()->get(SellerController::class)->create(new CreateSellerDto($unit['id'], 'La Jabalina')))['id'];
    }

    private function brandId(?int $sellerId = null, string $name = 'Cola Premium'): int
    {
        $sellerId ??= (int) self::getContainer()->get(SellerRepository::class)->findPlatform()?->id();
        $brand = Brand::create($sellerId, new Slug(strtolower(str_replace(' ', '-', $name))), $name);
        self::getContainer()->get(BrandRepository::class)->save($brand);

        return (int) $brand->id();
    }

    public function testItCreatesADraftForThePlatformSellerAndKeepsTheOriginalUnit(): void
    {
        $this->admin();

        $response = $this->controller()->create($this->createDto());
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('COLA-330', $data['sku']);
        $this->assertSame('draft', $data['status']);
        $this->assertSame(['service' => 'Gift Cards', 'subservice' => 'Retail', 'area' => null], [
            'service' => $data['service'],
            'subservice' => $data['subservice'],
            'area' => $data['area'],
        ]);
        $this->assertArrayNotHasKey('type', $data, 'El tipo lo dan el servicio y el subservicio, no un campo aparte.');
        $this->assertNull($data['value']);
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
            'area' => 'Supermarket',
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
        $this->assertSame('Supermarket', $spec->classification->area());
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
            service: 'Gift Cards',
            subservice: 'Retail',
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

        $skus = fn (Request $request) => array_column($this->body($this->controller()->list($request))['items'], 'sku');

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

        $connection->executeStatement("INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, created_at, updated_at) SELECT 'COLA-330', seller_id, 'draft', 'Gift Cards', 'Retail', '{}', 1, 1, 'g', 'weight', 'g', NOW(), NOW() FROM market_product LIMIT 1");
    }

    // ---- Productos con valor, como las gift cards (COMR-276) ----

    /**
     * @param array<string, mixed> $overrides
     */
    private function valueDto(string $sku = 'AMAZON-25', array $overrides = []): CreateProductDto
    {
        return new CreateProductDto(...($overrides + [
            'categoryIds' => [$this->defaultCategoryId()],
            'sku' => $sku,
            'texts' => ['es' => ['name' => 'Tarjeta Amazon'], 'en' => ['name' => 'Amazon card']],
            'service' => 'Gift Cards',
            'subservice' => 'Retail',
            'valueCurrency' => 'USD',
            'valueMin' => '25',
        ]));
    }

    public function testItCreatesAFixedValueProductWithoutPhysicalData(): void
    {
        $this->admin();

        $response = $this->controller()->create($this->valueDto());
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('Retail', $data['subservice']);
        $this->assertSame(['kind' => 'fixed', 'currency' => 'USD', 'min' => '25.00', 'max' => null], $data['value']);
        $this->assertNull($data['content']);
        $this->assertNull($data['deliveryBasis']);
        $this->assertNull($data['totalWeightG']);
        $this->assertTrue($data['seller']['isPlatform']);
        $this->assertArrayNotHasKey('type', $data);
    }

    public function testARangedValueSurvivesAPersistenceRoundTripWithItsExactAmounts(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->valueDto('STEAM', [
            'subservice' => 'Gaming',
            'valueMin' => '5.05',
            'valueMax' => '100.10',
        ])))['id'];
        $this->em->clear();

        $spec = $this->repository()->findById($id)?->spec();

        $this->assertTrue($spec?->isDigital());
        $this->assertSame('Gaming', $spec?->classification->subservice());
        $this->assertSame('5.05', $spec?->value?->minAmount()->toString());
        $this->assertSame('100.10', $spec?->value?->maxAmount()?->toString());
    }

    public function testAnAreaOnlyExistsUnderItsSubservice(): void
    {
        $this->admin();

        $wrong = $this->controller()->create($this->valueDto('STEAM', ['subservice' => 'Gaming', 'area' => 'Supermarket']));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $wrong->getStatusCode());
        $this->assertSame('INVALID_PRODUCT', $this->body($wrong)['error']['code']);

        $right = $this->body($this->controller()->create($this->valueDto('MERCADONA', ['area' => 'Supermarket'])));
        $this->assertSame('Supermarket', $right['area']);
    }

    public function testTheClassificationMustBeInTheControlledVocabulary(): void
    {
        $this->admin();

        foreach ([
            ['C-1', ['service' => 'Mobile']],
            ['C-2', ['service' => 'Retail']],
            ['C-3', ['subservice' => 'Electronics']],
            ['C-4', ['area' => 'Retail']],
        ] as [$sku, $overrides]) {
            $response = $this->controller()->create($this->valueDto($sku, $overrides));

            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $sku);
        }
    }

    public function testInvalidValuesAnswerUnprocessableEntity(): void
    {
        $this->admin();

        foreach ([
            ['A-1', ['valueMin' => '25,50']],
            ['A-2', ['valueMin' => '50', 'valueMax' => '10']],
            ['A-3', ['valueCurrency' => 'us']],
            ['A-4', ['contentValue' => 500.0, 'contentUnit' => 'g']],
        ] as [$sku, $overrides]) {
            $response = $this->controller()->create($this->valueDto($sku, $overrides));

            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $sku);
            $this->assertSame('INVALID_PRODUCT', $this->body($response)['error']['code'], $sku);
        }
    }

    public function testAProductWithValuePublishesLikeAnyProduct(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->valueDto()))['id'];

        $published = $this->body($this->controller()->changeStatus($id, new ChangeProductStatusDto('published')));

        $this->assertSame('published', $published['status']);
        $this->assertNotNull($published['value']);
    }

    public function testTheClassificationCanBeUpdated(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->valueDto()))['id'];

        $response = $this->controller()->update($id, new UpdateProductDto(
            texts: ['es' => ['name' => 'Tarjeta Steam']],
            service: 'Gift Cards',
            subservice: 'Gaming',
            valueCurrency: 'USD',
            valueMin: '25',
        ));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('Gaming', $this->body($response)['subservice']);
        $this->assertSame('AMAZON-25', $this->body($response)['sku']);
    }

    public function testTheDatabaseDoesNotAllowAProductThatHasNeitherContentNorValue(): void
    {
        $this->admin();
        $sellerId = (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $this->expectExceptionMessage('ck_market_product_shape');

        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, created_at, updated_at) VALUES ('VACIO', :s, 'draft', 'Gift Cards', 'Retail', '{}', NOW(), NOW())",
            ['s' => $sellerId],
        );
    }

    public function testTheDatabaseKeepsTheClassificationInTheControlledVocabulary(): void
    {
        $this->admin();
        $sellerId = (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $this->expectExceptionMessage('ck_market_product_classification');

        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, service, subservice, area, texts, value_currency, value_min_minor, created_at, updated_at) VALUES ('MAL', :s, 'draft', 'Gift Cards', 'Gaming', 'Supermarket', '{}', 'USD', 100, NOW(), NOW())",
            ['s' => $sellerId],
        );
    }

    // ---- Marca y categorías del producto (COMR-70) ----

    public function testItCreatesAProductWithItsBrandAndCategoriesAndAnswersThem(): void
    {
        $this->admin();
        $food = $this->categoryId('Alimentos');
        $rice = $this->categoryId('Arroz', $food);
        $brand = $this->brandId();

        $response = $this->controller()->create($this->createDto('ARROZ-1', ['brandId' => $brand, 'categoryIds' => [$rice, $food]]));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame($brand, $data['brand']['id']);
        $this->assertSame('Cola Premium', $data['brand']['name']);
        $this->assertSame([$food, $rice], array_column($data['categories'], 'id'));
        $this->assertSame(['Alimentos', 'Arroz'], array_column($data['categories'], 'nameEs'));
        $this->assertSame('Alimentos › Arroz', $data['categories'][1]['path']);
    }

    public function testTheBrandAndCategoriesSurviveAPersistenceRoundTrip(): void
    {
        $this->admin();
        $food = $this->categoryId('Alimentos');
        $brand = $this->brandId();
        $id = $this->body($this->controller()->create($this->createDto('ARROZ-1', ['brandId' => $brand, 'categoryIds' => [$food]])))['id'];
        $this->em->clear();

        $product = $this->repository()->findById($id);

        $this->assertSame($brand, $product?->brandId());
        $this->assertSame([$food], $product?->categoryIds());
    }

    public function testUpdatingReplacesTheCategoriesAndCanRemoveTheBrand(): void
    {
        $this->admin();
        $food = $this->categoryId('Alimentos');
        $drinks = $this->categoryId('Bebidas');
        $id = $this->body($this->controller()->create($this->createDto('ARROZ-1', ['brandId' => $this->brandId(), 'categoryIds' => [$food]])))['id'];

        $response = $this->controller()->update($id, new UpdateProductDto(
            texts: ['es' => ['name' => 'Arroz']],
            service: 'Gift Cards',
            subservice: 'Retail',
            contentValue: 2.5,
            contentUnit: 'lb',
            deliveryKind: 'weight',
            deliveryUnit: 'kg',
            categoryIds: [$drinks],
        ));
        $data = $this->body($response);
        $this->em->clear();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertNull($data['brand']);
        $this->assertSame([$drinks], array_column($data['categories'], 'id'));
        $this->assertSame([$drinks], $this->repository()->findById($id)?->categoryIds());
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_product_category WHERE product_id = ? AND category_id = ?', [$id, $food]));
    }

    public function testABrandOfAnotherSellerAnswersUnprocessableEntityWithItsCode(): void
    {
        $this->admin();
        $other = $this->otherSellerId();
        $foreignBrand = $this->brandId($other, 'Marca ajena');

        $response = $this->controller()->create($this->createDto('ARROZ-1', ['brandId' => $foreignBrand]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('PRODUCT_BRAND_SELLER_MISMATCH', $this->body($response)['error']['code']);
    }

    public function testAnUnknownBrandOrCategoryAnswersUnprocessableEntity(): void
    {
        $this->admin();

        $brand = $this->controller()->create($this->createDto('ARROZ-1', ['brandId' => 987654]));
        $category = $this->controller()->create($this->createDto('ARROZ-2', ['categoryIds' => [987654]]));

        $this->assertSame('PRODUCT_BRAND_INVALID', $this->body($brand)['error']['code']);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $brand->getStatusCode());
        $this->assertSame('PRODUCT_CATEGORY_INVALID', $this->body($category)['error']['code']);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $category->getStatusCode());
    }

    public function testAProductWithoutACategoryIsADraftThatCannotBePublished(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto('ARROZ-1', ['categoryIds' => []])))['id'];

        $response = $this->controller()->changeStatus($id, new ChangeProductStatusDto('published'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('INVALID_PRODUCT', $this->body($response)['error']['code']);
    }

    public function testListFiltersByBrandAndByCategoryWithItsSubcategories(): void
    {
        $this->admin();
        $food = $this->categoryId('Alimentos');
        $rice = $this->categoryId('Arroz', $food);
        $soap = $this->categoryId('Aseo');
        $brand = $this->brandId();
        $this->controller()->create($this->createDto('ARROZ-1', ['brandId' => $brand, 'categoryIds' => [$rice]]));
        $this->controller()->create($this->createDto('ACEITE-1', ['categoryIds' => [$food]]));
        $this->controller()->create($this->createDto('JABON-1', ['categoryIds' => [$soap]]));
        $skus = fn (array $query) => array_column($this->body($this->controller()->list(new Request($query)))['items'], 'sku');

        $this->assertSame(['ARROZ-1'], $skus(['brand' => (string) $brand]));
        $this->assertSame(['ACEITE-1', 'ARROZ-1'], $skus(['category' => (string) $food]));
        $this->assertSame(['ACEITE-1'], $skus(['category' => (string) $food, 'subcategories' => '0']));
        $this->assertSame(['ARROZ-1'], $skus(['category' => (string) $rice]));
    }

    public function testListIsPagedAndAnswersTheTotal(): void
    {
        $this->admin();
        foreach (['P-1', 'P-2', 'P-3', 'P-4', 'P-5'] as $sku) {
            $this->controller()->create($this->createDto($sku));
        }

        $data = $this->body($this->controller()->list(new Request(['q' => 'P-', 'page' => '2', 'perPage' => '2'])));

        $this->assertSame(['P-3', 'P-4'], array_column($data['items'], 'sku'));
        $this->assertSame(5, $data['total']);
        $this->assertSame(2, $data['page']);
        $this->assertSame(2, $data['perPage']);
    }

    public function testListSearchesByBarcodeToo(): void
    {
        $this->admin();
        $this->controller()->create($this->createDto('ARROZ-1', ['barcode' => '7501234567890']));
        $this->controller()->create($this->createDto('ARROZ-2'));

        $skus = array_column($this->body($this->controller()->list(new Request(['q' => '75012345'])))['items'], 'sku');

        $this->assertSame(['ARROZ-1'], $skus);
    }

    public function testTheDatabaseDoesNotAllowDeletingACategoryThatAProductUses(): void
    {
        $this->admin();
        $category = $this->categoryId('Alimentos');
        $this->controller()->create($this->createDto('ARROZ-1', ['categoryIds' => [$category]]));

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);

        $this->em->getConnection()->executeStatement('DELETE FROM market_category WHERE id = ?', [$category]);
    }

    public function testDeletingAProductDeletesItsCategoryLinks(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto('ARROZ-1')))['id'];
        $connection = $this->em->getConnection();

        $connection->executeStatement('DELETE FROM market_product WHERE id = ?', [$id]);

        $this->assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM market_product_category WHERE product_id = ?', [$id]));
    }

    public function testARelevanceMarkIsSavedReturnedAndFilterable(): void
    {
        $this->admin();
        $marked = $this->body($this->controller()->create($this->createDto('ARROZ-1')))['id'];
        $this->controller()->create($this->createDto('ARROZ-2'));
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $response = $this->controller()->setRelevance($marked, new SetProductRelevanceDto(2, $today, $tomorrow));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['level' => 2, 'from' => $today, 'until' => $tomorrow, 'active' => true], $this->body($response)['relevance']);
        $this->assertSame(
            ['level' => 2, 'from' => $today, 'until' => $tomorrow, 'active' => true],
            $this->body($this->controller()->get($marked, new Request()))['relevance'],
        );
        $only = $this->body($this->controller()->list(new Request(['relevant' => '1'])));
        $this->assertSame(1, $only['total']);
        $this->assertSame('ARROZ-1', $only['items'][0]['sku']);
        $this->assertSame(2, $this->body($this->controller()->list(new Request()))['total']);
    }

    public function testAnExpiredRelevanceIsKeptButNotListedAsRelevant(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto('ARROZ-1')))['id'];

        $this->controller()->setRelevance($id, new SetProductRelevanceDto(3, '2020-01-01', '2020-01-31'));

        $this->assertSame(0, $this->body($this->controller()->list(new Request(['relevant' => '1'])))['total']);
        $this->assertFalse($this->body($this->controller()->get($id, new Request()))['relevance']['active']);
    }

    public function testRelevanceIsRemovedWithLevelZero(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto('ARROZ-1')))['id'];
        $this->controller()->setRelevance($id, new SetProductRelevanceDto(2, null, null));

        $body = $this->body($this->controller()->setRelevance($id, new SetProductRelevanceDto(0, null, null)));

        $this->assertSame(['level' => 0, 'from' => null, 'until' => null, 'active' => false], $body['relevance']);
    }

    public function testAnInvalidRelevanceAnswersUnprocessableEntityAndAnUnknownProductNotFound(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto('ARROZ-1')))['id'];

        $invalid = $this->controller()->setRelevance($id, new SetProductRelevanceDto(2, '2026-10-31', '2026-10-12'));
        $unknown = $this->controller()->setRelevance(987654, new SetProductRelevanceDto(1, null, null));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $invalid->getStatusCode());
        $this->assertSame('INVALID_PRODUCT', $this->body($invalid)['error']['code']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $unknown->getStatusCode());
    }

    public function testItSuggestsTheSkuFromTheNameAndTheCategoryAndContinuesTheConsecutive(): void
    {
        $this->admin();
        $categoryId = $this->categoryId('Alimentos');

        $first = $this->controller()->suggestSku(new Request(['name' => 'Arroz blanco', 'category' => (string) $categoryId]));
        $this->assertSame(Response::HTTP_OK, $first->getStatusCode());
        $this->assertSame(['sku' => 'ALI-ARROZ-001'], $this->body($first));

        $this->controller()->create($this->createDto('ALI-ARROZ-001', ['categoryIds' => [$categoryId]]));
        $second = $this->body($this->controller()->suggestSku(new Request(['name' => 'Arroz integral', 'category' => (string) $categoryId])));

        $this->assertSame(['sku' => 'ALI-ARROZ-002'], $second);
    }

    public function testTheSkuSuggestionWorksWithoutACategoryAndRejectsAMissingNameOrAnUnknownCategory(): void
    {
        $this->admin();

        $generic = $this->body($this->controller()->suggestSku(new Request(['name' => 'Arroz'])));
        $noName = $this->controller()->suggestSku(new Request(['category' => '1']));
        $unknown = $this->controller()->suggestSku(new Request(['name' => 'Arroz', 'category' => '987654']));

        $this->assertSame(['sku' => 'GEN-ARROZ-001'], $generic);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $noName->getStatusCode());
        $this->assertSame('INVALID_PRODUCT', $this->body($noName)['error']['code']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $unknown->getStatusCode());
        $this->assertSame('CATEGORY_NOT_FOUND', $this->body($unknown)['error']['code']);
    }

    public function testTheListAndTheDetailCarryTheUrlsOfTheReferenceImageOrNull(): void
    {
        $this->admin();
        $with = $this->body($this->controller()->create($this->createDto('CON-FOTO')))['id'];
        $this->controller()->create($this->createDto('SIN-FOTO'));
        $file = str_repeat('a', 40);
        $gallery = ProductGallery::of([], 8);
        $gallery->add(ProductImage::create($with, $file, 'frente.png', 'image/png', 100, 10, 10));
        self::getContainer()->get(ProductImageRepository::class)->saveAll($gallery->images());

        $items = [];
        foreach ($this->body($this->controller()->list(new Request()))['items'] as $item) {
            $items[$item['sku']] = $item['image'];
        }
        $detail = $this->body($this->controller()->get($with, new Request()));

        $this->assertSame(['thumb', 'list', 'detail'], array_keys($items['CON-FOTO']));
        $this->assertStringContainsString($file, $items['CON-FOTO']['thumb']);
        $this->assertNull($items['SIN-FOTO']);
        $this->assertSame($items['CON-FOTO'], $detail['image']);
    }
}
