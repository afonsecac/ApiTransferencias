<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Infrastructure;

use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Infrastructure\Http\AddCostCenterDto;
use App\Market\Organizacion\Infrastructure\Http\CostCenterDto;
use App\Market\Organizacion\Infrastructure\Http\CreateOrgUnitDto;
use App\Market\Organizacion\Infrastructure\Http\OrgUnitController;
use App\Market\Organizacion\Infrastructure\Http\UpdateCostCenterDto;
use App\Market\Organizacion\Infrastructure\Http\UpdateOrgUnitDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-271: unidades organizativas y centros de costo contra Postgres real — persistencia y contrato HTTP.
 *
 * @covers \App\Market\Organizacion\Infrastructure\Persistence\DoctrineOrgUnitRepository
 * @covers \App\Market\Organizacion\Infrastructure\Http\OrgUnitController
 */
final class OrgUnitFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): OrgUnitController
    {
        return self::getContainer()->get(OrgUnitController::class);
    }

    private function repository(): OrgUnitRepository
    {
        return self::getContainer()->get(OrgUnitRepository::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function createDto(string $taxId = '12-3456789', string $country = 'US', array $types = ['seller'], ?array $fiscal = null): CreateOrgUnitDto
    {
        return new CreateOrgUnitDto(
            countryCode: $country,
            name: 'Acme Foods',
            legalName: 'Acme Foods LLC',
            taxId: $taxId,
            address: '1 Main St, New York',
            billingEmail: 'billing@acme.example',
            types: $types,
            fiscalData: $fiscal ?? ['ein' => '12-3456789', 'estado' => 'NY'],
            costCenters: [new CostCenterDto('gen', 'General', true)],
        );
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    public function testItCreatesAUnitValidatedAgainstTheSeededUsProfile(): void
    {
        $this->admin();

        $response = $this->controller()->create($this->createDto());
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('US', $data['countryCode']);
        $this->assertSame('USA', $data['countryCodeIso3']);
        $this->assertSame(['seller'], $data['types']);
        $this->assertSame('GEN', $data['costCenters'][0]['code']);
        $this->assertTrue($data['costCenters'][0]['principal']);
        $this->assertSame('NY', $data['fiscalData']['estado']);
    }

    public function testAUnitSurvivesAPersistenceRoundTrip(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto(types: ['seller', 'buyer'])))['id'];
        $this->em->clear();

        $unit = $this->repository()->findById($id);

        $this->assertNotNull($unit);
        $this->assertSame('12-3456789', $unit->taxId());
        $this->assertCount(2, $unit->types());
        $centers = $this->repository()->costCentersOf($unit);
        $this->assertCount(1, $centers);
        $this->assertSame('GEN', $centers[0]->code());
        $this->assertTrue($centers[0]->isPrincipal());
        $this->assertSame('NY', $unit->fiscalData()['estado']);
    }

    public function testInvalidFiscalDataAnswersUnprocessableEntityWithTheErrorsByKey(): void
    {
        $this->admin();

        $response = $this->controller()->create($this->createDto(fiscal: ['ein' => '123']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $error = $this->body($response)['error'];
        $this->assertSame('INVALID_FISCAL_DATA', $error['code']);
        $this->assertSame(['ein' => 'format', 'estado' => 'required'], $error['details']);
    }

    public function testTheSameTaxIdTwiceInACountryAnswersConflict(): void
    {
        $this->admin();
        $this->controller()->create($this->createDto());

        $response = $this->controller()->create($this->createDto());

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('ORG_UNIT_ALREADY_EXISTS', $this->body($response)['error']['code']);
    }

    public function testAnInvalidUnitAnswersUnprocessableEntity(): void
    {
        $this->admin();
        $dto = $this->createDto();
        $dto->setCostCenters([]);

        $response = $this->controller()->create($dto);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('INVALID_ORG_UNIT', $this->body($response)['error']['code']);
    }

    public function testListFiltersByTypeAndText(): void
    {
        $this->admin();
        $this->controller()->create($this->createDto(types: ['seller']));
        $this->controller()->create($this->createDto('98-7654321', types: ['carrier'], fiscal: ['ein' => '98-7654321', 'estado' => 'CA']));

        $all = $this->body($this->controller()->list(new Request()));
        $carriers = $this->body($this->controller()->list(new Request(['type' => 'carrier'])));
        $text = $this->body($this->controller()->list(new Request(['q' => '98-7654321'])));

        $this->assertGreaterThanOrEqual(2, count($all));
        $this->assertCount(1, $carriers);
        $this->assertSame('98-7654321', $carriers[0]['taxId']);
        $this->assertCount(1, $text);
    }

    public function testTheTextSearchAlsoFindsUnitsByTheCodeOrNameOfTheirCostCenters(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];
        $this->controller()->addCostCenter($id, new AddCostCenterDto('ruta-norte', 'Flota del norte'));
        $this->controller()->create($this->createDto('98-7654321', fiscal: ['ein' => '98-7654321', 'estado' => 'CA']));

        $byCode = $this->body($this->controller()->list(new Request(['q' => 'ruta-n'])));
        $byName = $this->body($this->controller()->list(new Request(['q' => 'flota'])));

        $this->assertSame([$id], array_column($byCode, 'id'));
        $this->assertSame([$id], array_column($byName, 'id'));
        $this->assertSame(['GEN', 'RUTA-NORTE'], array_column($byCode[0]['costCenters'], 'code'));
    }

    public function testTheListCarriesTheCostCentersOfEachUnitInOneGo(): void
    {
        $this->admin();
        $a = $this->body($this->controller()->create($this->createDto()))['id'];
        $b = $this->body($this->controller()->create($this->createDto('98-7654321', fiscal: ['ein' => '98-7654321', 'estado' => 'CA'])))['id'];
        $this->controller()->addCostCenter($b, new AddCostCenterDto('ALM', 'Almacén'));

        $byId = [];
        foreach ($this->body($this->controller()->list(new Request())) as $unit) {
            $byId[$unit['id']] = array_column($unit['costCenters'], 'code');
        }

        $this->assertSame(['GEN'], $byId[$a]);
        $this->assertSame(['ALM', 'GEN'], $byId[$b]);
    }

    public function testTheDatabaseItselfRefusesASecondPrincipalCenterInTheSameUnit(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];
        $this->controller()->addCostCenter($id, new AddCostCenterDto('ALM', 'Almacén'));
        $connection = $this->em->getConnection();

        // La restricción es diferida: se comprueba al confirmar la transacción, no al escribir.
        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $connection->executeStatement("UPDATE market_cost_center SET principal = TRUE WHERE org_unit_id = ?", [$id]);
        $connection->executeStatement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function testChangingThePrincipalSwapsBothFlagsInOneTransactionWithoutTrippingTheConstraint(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];
        $this->controller()->addCostCenter($id, new AddCostCenterDto('ALM', 'Almacén'));

        $response = $this->controller()->setPrincipal($id, 'ALM');
        $this->em->getConnection()->executeStatement('SET CONSTRAINTS ALL IMMEDIATE');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $principals = $this->em->getConnection()->fetchFirstColumn('SELECT code FROM market_cost_center WHERE org_unit_id = ? AND principal', [$id]);
        $this->assertSame(['ALM'], $principals);
    }

    public function testGetAndUpdateAndNotFound(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];

        $this->assertSame(Response::HTTP_OK, $this->controller()->get($id)->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->get(999999)->getStatusCode());

        $update = $this->controller()->update($id, new UpdateOrgUnitDto(
            name: 'Acme Foods Inc',
            legalName: 'Acme Foods Inc.',
            taxId: '12-3456789',
            address: '2 Main St',
            billingEmail: 'billing@acme.example',
            types: ['seller', 'warehouse_partner'],
            fiscalData: ['ein' => '12-3456789', 'estado' => 'NY'],
        ));
        $this->assertSame(Response::HTTP_OK, $update->getStatusCode(), (string) $update->getContent());
        $this->assertSame('Acme Foods Inc', $this->body($update)['name']);
        $this->assertSame('US', $this->body($update)['countryCode']);
    }

    public function testCostCenterLifecycleKeepsExactlyOnePrincipal(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create($this->createDto()))['id'];

        $added = $this->controller()->addCostCenter($id, new AddCostCenterDto('alm', 'Almacén'));
        $this->assertSame(Response::HTTP_CREATED, $added->getStatusCode());

        $principal = $this->controller()->setPrincipal($id, 'ALM');
        $centers = $this->body($principal)['costCenters'];
        $this->assertSame(['ALM'], array_values(array_map(static fn ($c) => $c['code'], array_filter($centers, static fn ($c) => $c['principal']))));

        $deactivateOld = $this->controller()->updateCostCenter($id, 'GEN', new UpdateCostCenterDto('General', false));
        $this->assertSame(Response::HTTP_OK, $deactivateOld->getStatusCode());

        $deactivatePrincipal = $this->controller()->updateCostCenter($id, 'ALM', new UpdateCostCenterDto('Almacén', false));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $deactivatePrincipal->getStatusCode());
        $this->assertSame('INVALID_ORG_UNIT', $this->body($deactivatePrincipal)['error']['code']);
    }

    public function testCostCenterOfAnUnknownUnitAnswersNotFound(): void
    {
        $this->admin();

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->addCostCenter(999999, new AddCostCenterDto('X', 'X'))->getStatusCode());
    }

    public function testTheRoutesLiveUnderTheDashboardPrefixAndNeedCredentials(): void
    {
        $router = self::getContainer()->get('router');
        $this->assertSame('/dashboard/api/market/org-units', $router->getRouteCollection()->get('market_org_units_list')?->getPath());

        $response = self::$kernel->handle(Request::create('/dashboard/api/market/org-units', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
