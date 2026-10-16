<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Infrastructure;

use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Infrastructure\Http\CreateWarehouseDto;
use App\Market\Inventario\Infrastructure\Http\UpdateWarehouseDto;
use App\Market\Inventario\Infrastructure\Http\WarehouseController;
use App\Market\Organizacion\Infrastructure\Http\CostCenterDto;
use App\Market\Organizacion\Infrastructure\Http\CreateOrgUnitDto;
use App\Market\Organizacion\Infrastructure\Http\OrgUnitController;
use App\Market\Organizacion\Infrastructure\Http\UpdateOrgUnitDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-285 contra Postgres real: el almacén principal sembrado, la persistencia y el contrato HTTP.
 *
 * @covers \App\Market\Inventario\Infrastructure\Persistence\DoctrineWarehouseRepository
 * @covers \App\Market\Inventario\Infrastructure\Persistence\OrganizacionWarehouseOwners
 * @covers \App\Market\Inventario\Infrastructure\Persistence\GeografiaWarehouseMunicipalities
 * @covers \App\Market\Inventario\Infrastructure\Http\WarehouseController
 */
final class WarehouseFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): WarehouseController
    {
        return self::getContainer()->get(WarehouseController::class);
    }

    /** @return array<string, mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    private function municipalityId(string $code): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_municipality WHERE code = :c', ['c' => $code]);
    }

    /** @param list<string> $types */
    private function unit(string $taxId, array $types): int
    {
        $response = self::getContainer()->get(OrgUnitController::class)->create(new CreateOrgUnitDto(
            countryCode: 'PA',
            name: 'Socio ' . $taxId,
            legalName: 'Socio ' . $taxId . ' SA',
            taxId: $taxId,
            address: 'Calle 50, Ciudad de Panamá',
            billingEmail: 'billing@socio.example',
            types: $types,
            fiscalData: ['ruc' => $taxId, 'dv' => '12'],
            costCenters: [new CostCenterDto('PRINCIPAL', 'Principal', true), new CostCenterDto('ALT', 'Alterno', false)],
        ));
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        return $this->body($response)['id'];
    }

    private function update(int $id, array $data): Response
    {
        return $this->controller()->update($id, new UpdateWarehouseDto(
            $data['name'] ?? 'Almacén',
            $data['address'] ?? null,
            $data['municipalityId'] ?? null,
            $data['orgUnitId'] ?? null,
            $data['costCenterId'] ?? null,
            $data['setsCoverage'] ?? false,
            $data['assemblesCombos'] ?? false,
            $data['seesSupplyCost'] ?? false,
        ));
    }

    public function testTheMainWarehouseIsSeededActiveOnThePlatformInTheConfiguredMunicipality(): void
    {
        $this->admin();
        $list = $this->body($this->controller()->list(new Request(query: ['status' => 'active'])));

        $main = array_values(array_filter($list, static fn ($w) => $w['isMain']));
        $this->assertCount(1, $main);
        $this->assertSame('active', $main[0]['status']);
        $this->assertSame('platform', $main[0]['regime']);
        $this->assertSame('Boyeros', $main[0]['municipality']['name']);
        $this->assertSame('La Habana', $main[0]['municipality']['provinceName']);
        $this->assertSame([], $main[0]['missing']);
    }

    public function testItCreatesADraftAndCompletesItThenActivates(): void
    {
        $this->admin();
        $partner = $this->unit('SOC-1', ['warehouse_partner']);
        $created = $this->controller()->create(new CreateWarehouseDto('Almacén Santiago'));
        $this->assertSame(Response::HTTP_CREATED, $created->getStatusCode(), (string) $created->getContent());
        $data = $this->body($created);
        $this->assertSame('draft', $data['status']);
        $this->assertSame('almacen-santiago', $data['slug']);
        $this->assertSame(['owner', 'cost_center', 'municipality'], $data['missing']);

        $updated = $this->update($data['id'], ['name' => 'Almacén Santiago', 'orgUnitId' => $partner, 'municipalityId' => $this->municipalityId('playa'), 'setsCoverage' => true]);
        $this->assertSame(Response::HTTP_OK, $updated->getStatusCode(), (string) $updated->getContent());
        $body = $this->body($updated);
        $this->assertSame('partner', $body['regime']);
        $this->assertSame('PRINCIPAL', $body['costCenter']['code']);
        $this->assertTrue($body['permissions']['setsCoverage']);

        $activated = $this->controller()->activate($data['id']);
        $this->assertSame(Response::HTTP_OK, $activated->getStatusCode(), (string) $activated->getContent());
        $this->assertSame('active', $this->body($activated)['status']);

        $this->em->clear();
        $stored = self::getContainer()->get(WarehouseRepository::class)->findById($data['id']);
        $this->assertTrue($stored?->isActive());
        $this->assertTrue($stored?->permissions()->setsCoverage);
    }

    public function testActivatingAnIncompleteDraftAnswers422WithWhatIsMissing(): void
    {
        $this->admin();
        $id = $this->body($this->controller()->create(new CreateWarehouseDto('Incompleto')))['id'];

        $response = $this->controller()->activate($id);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $error = $this->body($response)['error'];
        $this->assertSame('WAREHOUSE_INCOMPLETE', $error['code']);
        $this->assertSame(['owner', 'cost_center', 'municipality'], $error['missing']);
    }

    public function testAnOwnerThatIsNotAPartnerAnswers422(): void
    {
        $this->admin();
        $seller = $this->unit('SEL-1', ['seller']);
        $id = $this->body($this->controller()->create(new CreateWarehouseDto('Mal dueño')))['id'];

        $response = $this->update($id, ['orgUnitId' => $seller]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('WAREHOUSE_OWNER_INVALID', $this->body($response)['error']['code']);
    }

    public function testAnInactiveMunicipalityAnswers422(): void
    {
        $this->admin();
        $this->em->getConnection()->executeStatement("UPDATE market_municipality SET is_active = FALSE WHERE code = 'cerro'");
        $id = $this->body($this->controller()->create(new CreateWarehouseDto('Municipio inactivo')))['id'];

        $response = $this->update($id, ['municipalityId' => $this->municipalityId('cerro')]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('WAREHOUSE_MUNICIPALITY_INVALID', $this->body($response)['error']['code']);
    }

    public function testTheSlugIsUniqueAndTheSecondSameNameTakesASuffix(): void
    {
        $this->admin();
        $this->controller()->create(new CreateWarehouseDto('Repetido'));

        $second = $this->body($this->controller()->create(new CreateWarehouseDto('Repetido')));
        $this->assertSame('repetido-2', $second['slug']);

        $clash = $this->controller()->create(new CreateWarehouseDto('Otro', 'repetido'));
        $this->assertSame(Response::HTTP_CONFLICT, $clash->getStatusCode());
        $this->assertSame('WAREHOUSE_ALREADY_EXISTS', $this->body($clash)['error']['code']);
    }

    public function testTheMainWarehouseCannotBeDeactivatedNorDiscarded(): void
    {
        $this->admin();
        $main = array_values(array_filter($this->body($this->controller()->list(new Request())), static fn ($w) => $w['isMain']))[0];

        $deactivate = $this->controller()->deactivate($main['id']);
        $this->assertSame(Response::HTTP_CONFLICT, $deactivate->getStatusCode());
        $this->assertSame('MAIN_WAREHOUSE_PROTECTED', $this->body($deactivate)['error']['code']);

        $discard = $this->controller()->discard($main['id']);
        $this->assertSame(Response::HTTP_CONFLICT, $discard->getStatusCode());
    }

    public function testADraftIsDiscardedAndAnActivatedOneCannotBe(): void
    {
        $this->admin();
        $draft = $this->body($this->controller()->create(new CreateWarehouseDto('Borrador')))['id'];

        $this->assertSame(Response::HTTP_NO_CONTENT, $this->controller()->discard($draft)->getStatusCode());
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->controller()->get($draft)->getStatusCode());

        $partner = $this->unit('SOC-2', ['warehouse_partner']);
        $used = $this->body($this->controller()->create(new CreateWarehouseDto('Usado')))['id'];
        $this->update($used, ['orgUnitId' => $partner, 'municipalityId' => $this->municipalityId('playa')]);
        $this->controller()->activate($used);
        $this->controller()->deactivate($used);

        $this->assertSame(Response::HTTP_CONFLICT, $this->controller()->discard($used)->getStatusCode());
    }

    public function testTheDefaultsOfferTheConfiguredMunicipality(): void
    {
        $this->admin();

        $data = $this->body($this->controller()->defaults());

        $this->assertSame('Boyeros', $data['municipality']['name']);
    }

    public function testItFiltersByRegimeAndText(): void
    {
        $this->admin();
        $partner = $this->unit('SOC-3', ['warehouse_partner']);
        $id = $this->body($this->controller()->create(new CreateWarehouseDto('Santiago Norte')))['id'];
        $this->update($id, ['name' => 'Santiago Norte', 'orgUnitId' => $partner, 'municipalityId' => $this->municipalityId('santiago-de-cuba')]);

        $partners = $this->body($this->controller()->list(new Request(query: ['regime' => 'partner'])));
        $this->assertSame([$id], array_column($partners, 'id'));
        $this->assertSame([$id], array_column($this->body($this->controller()->list(new Request(query: ['q' => 'SANTIAGO']))), 'id'));
    }

    public function testAUnitWithWarehousesCannotLoseItsPartnerTypeSilently(): void
    {
        $this->admin();
        $partner = $this->unit('SOC-4', ['warehouse_partner', 'seller']);
        $id = $this->body($this->controller()->create(new CreateWarehouseDto('Del socio')))['id'];
        $this->update($id, ['orgUnitId' => $partner, 'municipalityId' => $this->municipalityId('playa')]);

        $response = self::getContainer()->get(OrgUnitController::class)->update($partner, new UpdateOrgUnitDto(
            'Socio SOC-4', 'Socio SOC-4 SA', 'SOC-4', 'Calle 50, Ciudad de Panamá', 'billing@socio.example', ['seller'], ['ruc' => 'SOC-4', 'dv' => '12'],
        ));

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('ORG_UNIT_TYPE_IN_USE', $this->body($response)['error']['code']);
    }
}
