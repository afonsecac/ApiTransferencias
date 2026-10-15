<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\Slug;
use App\Market\Catalogo\Infrastructure\Http\ChangeProductStatusDto;
use App\Market\Catalogo\Infrastructure\Http\CreateProductDto;
use App\Market\Catalogo\Infrastructure\Http\ProductController;
use App\Market\Catalogo\Infrastructure\Http\ProductPackageController;
use App\Market\Catalogo\Infrastructure\Http\PublishProductPackageDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-275 contra Postgres real: un producto suelto también es un paquete. Publicarlo crea (o actualiza) su paquete del
 * catálogo del comprador con la clasificación del producto, su contrato de precio por defecto y el enlace con el producto.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\LegacyProductPackages
 * @covers \App\Market\Catalogo\Infrastructure\Http\ProductPackageController
 */
final class ProductPackageFunctionalTest extends ProviderFunctionalTestCase
{
    private function products(): ProductController
    {
        return self::getContainer()->get(ProductController::class);
    }

    private function packages(): ProductPackageController
    {
        return self::getContainer()->get(ProductPackageController::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Un producto de gift cards en el área Supermarket, con categoría, ya publicado. */
    private function publishedProduct(string $sku = 'ARR-001', ?string $area = 'Supermarket'): int
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $category = Category::create(new Slug('cat-' . strtolower($sku)), null, 'General ' . $sku, null);
        self::getContainer()->get(CategoryRepository::class)->save($category);
        $created = $this->body($this->products()->create(new CreateProductDto(
            categoryIds: [(int) $category->id()],
            sku: $sku,
            texts: ['es' => ['name' => 'Arroz blanco', 'description' => 'Bolsa de 1 kg'], 'en' => ['name' => 'White rice']],
            service: 'Gift Cards',
            subservice: 'Retail',
            area: $area,
            contentValue: 1.0,
            contentUnit: 'kg',
            deliveryKind: 'weight',
            deliveryUnit: 'kg',
        )));
        $this->assertSame(Response::HTTP_OK, $this->products()->changeStatus($created['id'], new ChangeProductStatusDto('published'))->getStatusCode());

        return $created['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function packageRow(int $productId): ?array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT id, name, description, service, service_key, destination_amount, destination_currency, is_active, market_product_id
             FROM communication_package WHERE market_product_id = ?',
            [$productId],
        );

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contractsOf(int $packageId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.tenant_id, c.price, c.currency, c.service_key, c.end_at
             FROM communication_contract c JOIN communication_contract_package cp ON cp.communication_contract_id = c.id
             WHERE cp.communication_package_id = ? ORDER BY c.id',
            [$packageId],
        );
    }

    public function testPublishingCreatesThePackageWithTheClassificationOfTheProduct(): void
    {
        $productId = $this->publishedProduct();

        $response = $this->packages()->publish($productId, new PublishProductPackageDto('12.50', 'usd'));
        $body = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame(['price' => '12.50', 'currency' => 'USD', 'isActive' => true], array_diff_key($body, ['packageId' => 1]));
        $row = $this->packageRow($productId);
        $this->assertNotNull($row);
        $this->assertSame($row['id'], $body['packageId']);
        $this->assertSame('Arroz blanco', $row['name']);
        $this->assertSame('Bolsa de 1 kg', $row['description']);
        $this->assertSame(
            ['name' => 'Gift Cards', 'subservice' => ['name' => 'Retail'], 'area' => ['name' => 'Supermarket']],
            json_decode((string) $row['service'], true),
        );
        $this->assertSame('Gift Cards|Retail|Supermarket', $row['service_key']);
        $this->assertTrue($row['is_active']);
        $this->assertSame('USD', $row['destination_currency']);
    }

    public function testThePackageGetsADefaultContractWithThePrice(): void
    {
        $productId = $this->publishedProduct();

        $this->packages()->publish($productId, new PublishProductPackageDto('12.50', 'USD'));
        $contracts = $this->contractsOf((int) $this->packageRow($productId)['id']);

        $this->assertCount(1, $contracts);
        $this->assertNull($contracts[0]['tenant_id'], 'Un contrato por defecto, sin cuenta.');
        $this->assertSame(12.5, (float) $contracts[0]['price']);
        $this->assertSame('USD', $contracts[0]['currency']);
        $this->assertSame('Gift Cards|Retail|Supermarket', $contracts[0]['service_key']);
        $this->assertNull($contracts[0]['end_at']);
    }

    public function testPublishingAgainChangesThePriceButKeepsThePackageAndLeavesOneContract(): void
    {
        $productId = $this->publishedProduct();
        $first = $this->body($this->packages()->publish($productId, new PublishProductPackageDto('12.50', 'USD')));

        $second = $this->body($this->packages()->publish($productId, new PublishProductPackageDto('9.99', 'USD')));

        $this->assertSame($first['packageId'], $second['packageId']);
        $contracts = $this->contractsOf($first['packageId']);
        $this->assertCount(1, $contracts);
        $this->assertSame(9.99, (float) $contracts[0]['price']);
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM communication_package WHERE market_product_id = ?', [$productId]));
    }

    public function testTwoProductsWithTheSamePriceShareTheContract(): void
    {
        $a = $this->publishedProduct('ARR-001');
        $b = $this->publishedProduct('FRI-001');

        $this->packages()->publish($a, new PublishProductPackageDto('5.00', 'USD'));
        $this->packages()->publish($b, new PublishProductPackageDto('5.00', 'USD'));

        $contractA = $this->contractsOf((int) $this->packageRow($a)['id'])[0]['id'];
        $contractB = $this->contractsOf((int) $this->packageRow($b)['id'])[0]['id'];
        $this->assertSame($contractA, $contractB);
    }

    public function testAProductWithoutAreaGetsATwoPartCategory(): void
    {
        $productId = $this->publishedProduct('TAR-001', null);

        $this->packages()->publish($productId, new PublishProductPackageDto('25.00', 'USD'));

        $this->assertSame('Gift Cards|Retail', $this->packageRow($productId)['service_key']);
    }

    public function testRetiringDeactivatesThePackageAndKeepsIt(): void
    {
        $productId = $this->publishedProduct();
        $published = $this->body($this->packages()->publish($productId, new PublishProductPackageDto('12.50', 'USD')));

        $response = $this->packages()->retire($productId);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($this->body($response)['isActive']);
        $this->assertFalse($this->packageRow($productId)['is_active']);
        $again = $this->body($this->packages()->publish($productId, new PublishProductPackageDto('12.50', 'USD')));
        $this->assertSame($published['packageId'], $again['packageId']);
        $this->assertTrue($this->packageRow($productId)['is_active']);
    }

    public function testGettingThePackageOfAProductThatWasNeverOfferedAnswersNotFound(): void
    {
        $productId = $this->publishedProduct();

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->packages()->get($productId)->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->packages()->retire($productId)->getStatusCode());
    }

    public function testADraftProductCannotBeOfferedAndAnUnknownOneIsNotFound(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $draft = $this->body($this->products()->create(new CreateProductDto(
            sku: 'BOR-001',
            texts: ['es' => ['name' => 'Borrador']],
            service: 'Gift Cards',
            subservice: 'Retail',
            contentValue: 1.0,
            contentUnit: 'kg',
            deliveryKind: 'weight',
            deliveryUnit: 'kg',
        )))['id'];

        $rejected = $this->packages()->publish($draft, new PublishProductPackageDto('5.00', 'USD'));
        $unknown = $this->packages()->publish(987654, new PublishProductPackageDto('5.00', 'USD'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $rejected->getStatusCode());
        $this->assertSame('INVALID_PRODUCT', $this->body($rejected)['error']['code']);
        $this->assertNull($this->packageRow($draft));
        $this->assertSame(Response::HTTP_NOT_FOUND, $unknown->getStatusCode());
    }
}
