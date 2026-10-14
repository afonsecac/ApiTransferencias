<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\SellerRepository;
use App\Market\Catalogo\Infrastructure\Http\CreateSellerDto;
use App\Market\Catalogo\Infrastructure\Http\SellerController;
use App\Market\Catalogo\Infrastructure\Http\SetSellerActiveDto;
use App\Market\Catalogo\Infrastructure\Http\UpdateSellerDto;
use App\Market\Organizacion\Infrastructure\Http\CostCenterDto;
use App\Market\Organizacion\Infrastructure\Http\CreateOrgUnitDto;
use App\Market\Organizacion\Infrastructure\Http\OrgUnitController;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-103 contra Postgres real: persistencia del vendedor, su vínculo con la unidad organizativa y el contrato HTTP.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\DoctrineSellerRepository
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\OrganizacionSellerOrgUnits
 * @covers \App\Market\Catalogo\Infrastructure\Http\SellerController
 */
final class SellerFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): SellerController
    {
        return self::getContainer()->get(SellerController::class);
    }

    private function repository(): SellerRepository
    {
        return self::getContainer()->get(SellerRepository::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $types
     * @return array{id: int, centerId: int}
     */
    private function unit(string $taxId = 'JAB-1', array $types = ['seller']): array
    {
        $response = self::getContainer()->get(OrgUnitController::class)->create(new CreateOrgUnitDto(
            countryCode: 'PA',
            name: 'La Jabalina SA',
            legalName: 'La Jabalina SA',
            taxId: $taxId,
            address: 'Calle 50, Ciudad de Panamá',
            billingEmail: 'billing@jabalina.example',
            types: $types,
            fiscalData: ['ruc' => $taxId, 'dv' => '12'],
            costCenters: [new CostCenterDto('PRINCIPAL', 'Principal', true), new CostCenterDto('RETAIL', 'Retail', false)],
        ));
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $data = $this->body($response);

        return ['id' => $data['id'], 'centerId' => 0];
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    public function testItCreatesASellerForASellerUnitAndAnswersItsUnitAndCostCenter(): void
    {
        $this->admin();
        $unit = $this->unit();

        $response = $this->controller()->create(new CreateSellerDto($unit['id'], 'La Jabalina'));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('la-jabalina', $data['slug']);
        $this->assertFalse($data['isPlatform']);
        $this->assertTrue($data['isActive']);
        $this->assertSame($unit['id'], $data['orgUnit']['id']);
        $this->assertSame('La Jabalina SA', $data['orgUnit']['name']);
        $this->assertSame('PRINCIPAL', $data['costCenter']['code']);
    }

    public function testASellerSurvivesAPersistenceRoundTrip(): void
    {
        $this->admin();
        $unit = $this->unit();
        $id = $this->body($this->controller()->create(new CreateSellerDto($unit['id'], 'La Jabalina', 'jabalina')))['id'];
        $this->em->clear();

        $seller = $this->repository()->findById($id);

        $this->assertNotNull($seller);
        $this->assertSame('jabalina', $seller->slug()->value());
        $this->assertSame($unit['id'], $seller->orgUnitId());
        $this->assertSame($seller, $this->repository()->findBySlug('jabalina'));
        $this->assertSame($seller, $this->repository()->findByOrgUnit($unit['id']));
    }

    public function testItChargesTheSellerToAnotherActiveCostCenterOfItsUnit(): void
    {
        $this->admin();
        $unit = $this->unit();
        $created = $this->body($this->controller()->create(new CreateSellerDto($unit['id'], 'La Jabalina')));
        $retail = array_values(array_filter(
            $this->body(self::getContainer()->get(OrgUnitController::class)->get($unit['id']))['costCenters'],
            static fn (array $c) => $c['code'] === 'RETAIL',
        ));
        $this->assertCount(1, $retail);
        $retailId = (int) $this->em->getConnection()->fetchOne(
            'SELECT id FROM market_cost_center WHERE org_unit_id = :u AND code = :c',
            ['u' => $unit['id'], 'c' => 'RETAIL'],
        );

        $response = $this->controller()->update($created['id'], new UpdateSellerDto('La Jabalina Foods', $retailId));
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('La Jabalina Foods', $data['name']);
        $this->assertSame('RETAIL', $data['costCenter']['code']);
        $this->assertSame('la-jabalina', $data['slug']);
    }

    public function testAUnitWithoutTheSellerTypeAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $unit = $this->unit('BUY-1', ['buyer']);

        $response = $this->controller()->create(new CreateSellerDto($unit['id'], 'Comprador'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('SELLER_ORG_UNIT_INVALID', $this->body($response)['error']['code']);
    }

    public function testAnUnknownUnitAnswersUnprocessableEntity(): void
    {
        $this->admin();

        $response = $this->controller()->create(new CreateSellerDto(987654, 'Fantasma'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('SELLER_ORG_UNIT_INVALID', $this->body($response)['error']['code']);
    }

    public function testADuplicatedSlugOrUnitAnswersConflict(): void
    {
        $this->admin();
        $unit = $this->unit();
        $this->controller()->create(new CreateSellerDto($unit['id'], 'La Jabalina', 'jabalina'));

        $sameUnit = $this->controller()->create(new CreateSellerDto($unit['id'], 'Otro', 'otro'));
        $this->assertSame(Response::HTTP_CONFLICT, $sameUnit->getStatusCode());
        $this->assertSame('SELLER_ALREADY_EXISTS', $this->body($sameUnit)['error']['code']);

        $other = $this->unit('JAB-2');
        $sameSlug = $this->controller()->create(new CreateSellerDto($other['id'], 'Otra', 'jabalina'));
        $this->assertSame(Response::HTTP_CONFLICT, $sameSlug->getStatusCode());
    }

    public function testAnInvalidNameOrSlugAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $unit = $this->unit();

        $slug = $this->controller()->create(new CreateSellerDto($unit['id'], 'Nombre', 'Mal Slug'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $slug->getStatusCode());
        $this->assertSame('INVALID_SELLER', $this->body($slug)['error']['code']);
    }

    public function testItDeactivatesAndReactivatesASeller(): void
    {
        $this->admin();
        $unit = $this->unit();
        $id = $this->body($this->controller()->create(new CreateSellerDto($unit['id'], 'La Jabalina')))['id'];

        $off = $this->body($this->controller()->setActive($id, new SetSellerActiveDto(false)));
        $this->em->clear();
        $this->assertFalse($off['isActive']);
        $this->assertFalse($this->repository()->findById($id)?->isActive());

        $on = $this->body($this->controller()->setActive($id, new SetSellerActiveDto(true)));
        $this->assertTrue($on['isActive']);
    }

    public function testAnUnknownSellerAnswersNotFound(): void
    {
        $this->admin();

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->get(987654)->getStatusCode());
        $this->assertSame('SELLER_NOT_FOUND', $this->body($this->controller()->get(987654))['error']['code']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->update(987654, new UpdateSellerDto('X'))->getStatusCode());
    }

    public function testThereIsExactlyOnePlatformSellerAndItIsProtected(): void
    {
        $this->admin();
        $platform = $this->repository()->findPlatform();
        $this->assertNotNull($platform, 'La migración siembra el vendedor de plataforma.');
        $this->assertTrue($platform->isActive());
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_seller WHERE is_platform'));

        $response = $this->controller()->setActive((int) $platform->id(), new SetSellerActiveDto(false));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('PLATFORM_SELLER_PROTECTED', $this->body($response)['error']['code']);
    }

    public function testTheDatabaseRefusesASecondPlatformSellerAndADeactivatedOne(): void
    {
        $connection = $this->em->getConnection();
        $unit = $this->unit();
        $centerId = (int) $connection->fetchOne('SELECT id FROM market_cost_center WHERE org_unit_id = :u AND principal', ['u' => $unit['id']]);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $connection->executeStatement(
            "INSERT INTO market_seller (org_unit_id, cost_center_id, slug, name, is_platform, is_active, created_at, updated_at) VALUES (:u, :c, 'segunda', 'Segunda', TRUE, TRUE, NOW(), NOW())",
            ['u' => $unit['id'], 'c' => $centerId],
        );
    }

    public function testTheDatabaseRefusesToDeactivateThePlatformSeller(): void
    {
        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $this->expectExceptionMessage('ck_market_seller_platform_active');

        $this->em->getConnection()->executeStatement('UPDATE market_seller SET is_active = FALSE WHERE is_platform');
    }

    public function testListFiltersByTextAndState(): void
    {
        $this->admin();
        $a = $this->unit('JAB-1');
        $b = $this->unit('JAB-2');
        $this->controller()->create(new CreateSellerDto($a['id'], 'Garajito Foods', 'garajito-foods'));
        $second = $this->body($this->controller()->create(new CreateSellerDto($b['id'], 'Zapatería Sol', 'zapateria-sol')));
        $this->controller()->setActive($second['id'], new SetSellerActiveDto(false));

        $names = static fn (array $rows) => array_column($rows, 'name');
        $this->assertContains('Garajito Foods', $names($this->body($this->controller()->list(new Request(['q' => 'garaj'])))));
        $this->assertNotContains('Zapatería Sol', $names($this->body($this->controller()->list(new Request(['active' => 'true'])))));
        $this->assertContains('Zapatería Sol', $names($this->body($this->controller()->list(new Request(['active' => 'false'])))));
    }
}
