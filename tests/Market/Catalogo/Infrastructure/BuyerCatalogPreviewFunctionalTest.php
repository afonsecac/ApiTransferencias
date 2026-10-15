<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Entity\Account;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\Relevance;
use App\Market\Catalogo\Domain\Slug;
use App\Market\Catalogo\Infrastructure\Http\BuyerCatalogPreviewController;
use App\Market\Catalogo\Infrastructure\Http\ChangeProductStatusDto;
use App\Market\Catalogo\Infrastructure\Http\CreateProductDto;
use App\Market\Catalogo\Infrastructure\Http\ProductController;
use App\Market\Catalogo\Infrastructure\Http\ProductPackageController;
use App\Market\Catalogo\Infrastructure\Http\PublishProductPackageDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-275: la vista previa del catálogo del comprador deja ver, desde el dashboard, lo que recibe una cuenta: primero su
 * routing de proveedores y, sobre eso, el filtro de servicio, subservicio y área. Contra Postgres real.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Http\BuyerCatalogPreviewController
 */
final class BuyerCatalogPreviewFunctionalTest extends ProviderFunctionalTestCase
{
    private function preview(array $query): Response
    {
        return self::getContainer()->get(BuyerCatalogPreviewController::class)->preview(new Request($query));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function account(): Account
    {
        return $this->createAccount($this->createClient(), $this->createEnvironment());
    }

    /** Un producto de una categoría, publicado y ofrecido al comprador a ese precio. Devuelve el id del producto. */
    private function offered(string $sku, string $price, string $subservice = 'Retail', ?string $area = 'Supermarket'): int
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $category = Category::create(new Slug('cat-' . strtolower($sku)), null, 'Alimentos', null);
        self::getContainer()->get(CategoryRepository::class)->save($category);
        $products = self::getContainer()->get(ProductController::class);
        $created = json_decode((string) $products->create(new CreateProductDto(
            categoryIds: [(int) $category->id()],
            sku: $sku,
            texts: ['es' => ['name' => 'Producto ' . $sku]],
            service: 'Gift Cards',
            subservice: $subservice,
            area: $area,
            contentValue: 1.0,
            contentUnit: 'kg',
            deliveryKind: 'weight',
            deliveryUnit: 'kg',
        ))->getContent(), true);
        $products->changeStatus($created['id'], new ChangeProductStatusDto('published'));
        self::getContainer()->get(ProductPackageController::class)->publish($created['id'], new PublishProductPackageDto($price, 'USD'));

        return $created['id'];
    }

    public function testItShowsWhatAnAccountWithoutRoutingReceives(): void
    {
        $this->offered('ARR-001', '12.50');

        $response = $this->preview(['account' => (string) $this->account()->getId()]);
        $body = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame(1, $body['total']);
        $item = $body['items'][0];
        $this->assertSame('market', $item['kind']);
        $this->assertSame('Producto ARR-001', $item['name']);
        $this->assertSame(['service' => 'Gift Cards', 'subservice' => 'Retail', 'area' => 'Supermarket'], $item['classification']);
        $this->assertSame(12.5, $item['price']);
        $this->assertSame('USD', $item['currency']);
        $this->assertArrayNotHasKey('relevance', $item);
    }

    public function testItFiltersByServiceSubserviceAndArea(): void
    {
        $this->offered('ARR-001', '12.50');
        $this->offered('TAR-001', '25.00', 'Gaming', null);
        $account = (string) $this->account()->getId();

        $all = $this->body($this->preview(['account' => $account]));
        $gaming = $this->body($this->preview(['account' => $account, 'service' => 'Gift Cards', 'subservice' => 'Gaming']));
        $area = $this->body($this->preview(['account' => $account, 'area' => 'Supermarket']));
        $none = $this->body($this->preview(['account' => $account, 'service' => 'Utilities']));

        $this->assertSame(2, $all['total']);
        $this->assertSame(['TAR-001'], array_map(static fn (array $i): string => $i['sku'], $gaming['items']));
        $this->assertSame(['ARR-001'], array_map(static fn (array $i): string => $i['sku'], $area['items']));
        $this->assertSame(0, $none['total']);
    }

    public function testTheRoutingOfTheAccountIsAppliedBeforeTheFilter(): void
    {
        $this->offered('ARR-001', '12.50');
        $client = $this->createClient();
        $account = $this->createAccount($client, $this->createEnvironment());
        $this->createRouting($client, 'ETECSA', null, null, true, null, null, 'Mobile', 'AIRTIME');

        $body = $this->body($this->preview(['account' => (string) $account->getId(), 'service' => 'Gift Cards']));

        $this->assertSame(0, $body['total'], 'La cuenta solo cubre Mobile: no ve Gift Cards ni aunque lo pida en el filtro.');
    }

    public function testItShowsTheRelevanceWhileItIsInForce(): void
    {
        $productId = $this->offered('ARR-001', '12.50');
        $products = self::getContainer()->get(ProductRepository::class);
        $product = $products->findById($productId);
        $product->setRelevance(Relevance::of(3, null, null));
        $products->save($product);

        $item = $this->body($this->preview(['account' => (string) $this->account()->getId()]))['items'][0];

        $this->assertSame(['level' => 3], $item['relevance']);
    }

    public function testItIsPaged(): void
    {
        $this->offered('ARR-001', '1.00');
        $this->offered('ARR-002', '2.00');
        $this->offered('ARR-003', '3.00');
        $account = (string) $this->account()->getId();

        $page = $this->body($this->preview(['account' => $account, 'page' => '2', 'perPage' => '2']));

        $this->assertSame(3, $page['total']);
        $this->assertSame(2, $page['page']);
        $this->assertSame(2, $page['perPage']);
        $this->assertCount(1, $page['items']);
    }

    public function testAnUnknownOrMissingAccountIsRejected(): void
    {
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->preview(['account' => '987654'])->getStatusCode());
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->preview([])->getStatusCode());
    }
}
