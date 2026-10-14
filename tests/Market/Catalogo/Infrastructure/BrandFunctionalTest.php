<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\BrandRepository;
use App\Market\Catalogo\Domain\SellerRepository;
use App\Market\Catalogo\Infrastructure\Http\BrandController;
use App\Market\Catalogo\Infrastructure\Http\CreateBrandDto;
use App\Market\Catalogo\Infrastructure\Http\SetBrandActiveDto;
use App\Market\Catalogo\Infrastructure\Http\UpdateBrandDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-70 contra Postgres real: persistencia de la marca, su vínculo con el vendedor y el contrato HTTP.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\DoctrineBrandRepository
 * @covers \App\Market\Catalogo\Infrastructure\Http\BrandController
 */
final class BrandFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): BrandController
    {
        return self::getContainer()->get(BrandController::class);
    }

    private function repository(): BrandRepository
    {
        return self::getContainer()->get(BrandRepository::class);
    }

    private function platformId(): int
    {
        return (int) self::getContainer()->get(SellerRepository::class)->findPlatform()?->id();
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

    public function testItCreatesABrandOfTheSellerAndAnswersIt(): void
    {
        $this->admin();

        $response = $this->controller()->create(new CreateBrandDto($this->platformId(), 'Cola Premium'));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('cola-premium', $data['slug']);
        $this->assertSame('Cola Premium', $data['name']);
        $this->assertTrue($data['isActive']);
        $this->assertSame($this->platformId(), $data['seller']['id']);
        $this->assertTrue($data['seller']['isPlatform']);
    }

    public function testABrandSurvivesAPersistenceRoundTrip(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create(new CreateBrandDto($this->platformId(), 'Cola Premium', 'cola-p')))['id'];
        $this->em->clear();

        $brand = $this->repository()->findById($id);

        $this->assertNotNull($brand);
        $this->assertSame('cola-p', $brand->slug()->value());
        $this->assertSame($this->platformId(), $brand->sellerId());
        $this->assertSame($brand, $this->repository()->findBySlug('cola-p'));
    }

    public function testABrandNeedsAnExistingActiveSeller(): void
    {
        $this->admin();

        $response = $this->controller()->create(new CreateBrandDto(987654, 'Sin vendedor'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('BRAND_SELLER_INVALID', $this->body($response)['error']['code']);
    }

    public function testTheSlugIsUniqueAndInvalidDataAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $this->controller()->create(new CreateBrandDto($this->platformId(), 'Cola Premium'));

        $duplicated = $this->controller()->create(new CreateBrandDto($this->platformId(), 'Otra', 'cola-premium'));
        $this->assertSame(Response::HTTP_CONFLICT, $duplicated->getStatusCode());
        $this->assertSame('BRAND_ALREADY_EXISTS', $this->body($duplicated)['error']['code']);

        foreach ([['  ', null], ['Marca', 'Mal Slug']] as [$name, $slug]) {
            $response = $this->controller()->create(new CreateBrandDto($this->platformId(), $name, $slug));
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
            $this->assertSame('INVALID_BRAND', $this->body($response)['error']['code']);
        }
    }

    public function testItRenamesABrandButNotItsSlugNorItsSeller(): void
    {
        $this->admin();
        $created = $this->body($this->controller()->create(new CreateBrandDto($this->platformId(), 'Cola Premium')));

        $response = $this->controller()->update($created['id'], new UpdateBrandDto('Cola Premium Plus'));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('Cola Premium Plus', $data['name']);
        $this->assertSame('cola-premium', $data['slug']);
        $this->assertSame($created['seller']['id'], $data['seller']['id']);
    }

    public function testItDeactivatesAndReactivatesABrand(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create(new CreateBrandDto($this->platformId(), 'Cola Premium')))['id'];

        $off = $this->body($this->controller()->setActive($id, new SetBrandActiveDto(false)));
        $this->em->clear();
        $this->assertFalse($off['isActive']);
        $this->assertFalse($this->repository()->findById($id)?->isActive());

        $this->assertTrue($this->body($this->controller()->setActive($id, new SetBrandActiveDto(true)))['isActive']);
    }

    public function testAnUnknownBrandAnswersNotFound(): void
    {
        $this->admin();

        $response = $this->controller()->get(987654);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('BRAND_NOT_FOUND', $this->body($response)['error']['code']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->update(987654, new UpdateBrandDto('X'))->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->setActive(987654, new SetBrandActiveDto(true))->getStatusCode());
    }

    public function testListFiltersByTextSellerAndState(): void
    {
        $this->admin();
        $cola = $this->body($this->controller()->create(new CreateBrandDto($this->platformId(), 'Cola Premium')))['id'];
        $agua = $this->body($this->controller()->create(new CreateBrandDto($this->platformId(), 'Agua Pura')))['id'];
        $this->controller()->setActive($agua, new SetBrandActiveDto(false));
        $names = fn (Request $request) => array_column($this->body($this->controller()->list($request)), 'name');

        $this->assertContains('Cola Premium', $names(new Request(['q' => 'cola'])));
        $this->assertNotContains('Agua Pura', $names(new Request(['active' => 'true'])));
        $this->assertContains('Agua Pura', $names(new Request(['active' => 'false'])));
        $this->assertContains('Cola Premium', $names(new Request(['seller' => (string) $this->platformId()])));
        $this->assertSame([], $names(new Request(['seller' => '987654'])));
        $this->assertNotNull($cola);
    }

    public function testTheDatabaseKeepsTheSlugUnique(): void
    {
        $this->admin();
        $this->controller()->create(new CreateBrandDto($this->platformId(), 'Cola Premium'));

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_brand (seller_id, slug, name, is_active, created_at, updated_at) VALUES (:s, 'cola-premium', 'Dup', TRUE, NOW(), NOW())",
            ['s' => $this->platformId()],
        );
    }

    public function testTheDatabaseKeepsTheSellerReferenced(): void
    {
        $this->expectException(\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException::class);

        $this->em->getConnection()->executeStatement("INSERT INTO market_brand (seller_id, slug, name, is_active, created_at, updated_at) VALUES (987654, 'otra', 'Otra', TRUE, NOW(), NOW())");
    }
}
