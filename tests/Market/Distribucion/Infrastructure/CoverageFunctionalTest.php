<?php

declare(strict_types=1);

namespace App\Tests\Market\Distribucion\Infrastructure;

use App\Market\Distribucion\Infrastructure\Http\BulkCoverageDto;
use App\Market\Distribucion\Infrastructure\Http\CoverageController;
use App\Market\Distribucion\Infrastructure\Http\SetCoverageActiveDto;
use App\Market\Distribucion\Infrastructure\Http\SetDeliveryCurrencyDto;
use App\Market\Distribucion\Infrastructure\Http\UpsertCoverageDto;
use App\Market\Inventario\Infrastructure\Http\CreateWarehouseDto;
use App\Market\Inventario\Infrastructure\Http\WarehouseController;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-72 contra Postgres real: la cobertura y su precio por municipio, el almacén que atiende y el contrato HTTP.
 *
 * @covers \App\Market\Distribucion\Infrastructure\Persistence\DoctrineCoverageRepository
 * @covers \App\Market\Distribucion\Infrastructure\Persistence\DoctrineDeliveryCurrencies
 * @covers \App\Market\Distribucion\Infrastructure\Persistence\InventarioDeliveryWarehouses
 * @covers \App\Market\Distribucion\Infrastructure\Persistence\GeografiaDeliveryMunicipalities
 * @covers \App\Market\Distribucion\Infrastructure\Http\CoverageController
 */
final class CoverageFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): CoverageController
    {
        return self::getContainer()->get(CoverageController::class);
    }

    /** @return array<mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function admin(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
    }

    private function mainWarehouseId(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_warehouse WHERE is_main');
    }

    private function municipalityId(string $code): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_municipality WHERE code = :c', ['c' => $code]);
    }

    private function provinceId(string $code): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_province WHERE code = :c', ['c' => $code]);
    }

    private function dto(string $perKg = '0.35', string $perL = '0.40', string $min = '2', ?string $max = '12', int $priority = 1, bool $zero = false, bool $noMax = false): UpsertCoverageDto
    {
        return new UpsertCoverageDto($perKg, $perL, $min, $max, $priority, $zero, $noMax);
    }

    public function testItCreatesTheCoverageAndListsItWithMunicipalityAndCurrency(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();

        $created = $this->controller()->upsert($w, $this->municipalityId('boyeros'), $this->dto());
        $this->assertSame(Response::HTTP_OK, $created->getStatusCode(), (string) $created->getContent());

        $list = $this->body($this->controller()->list($w));
        $this->assertSame('EUR', $list['currency']);
        $this->assertCount(1, $list['rows']);
        $row = $list['rows'][0];
        $this->assertSame('Boyeros', $row['municipality']['name']);
        $this->assertSame('La Habana', $row['municipality']['provinceName']);
        $this->assertSame('0.3500', $row['ratePerKg']);
        $this->assertSame('12.0000', $row['maxPrice']);
        $this->assertSame('linear', $row['rateMode']);
        $this->assertTrue($row['active']);
        $this->assertTrue($row['isServing']);
    }

    public function testACoverageSurvivesAPersistenceRoundTrip(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $this->controller()->upsert($w, $this->municipalityId('playa'), $this->dto(perKg: '0.1234', max: null, noMax: true, priority: 3));
        $this->em->clear();

        $row = $this->body($this->controller()->list($w))['rows'][0];

        $this->assertSame('0.1234', $row['ratePerKg']);
        $this->assertNull($row['maxPrice']);
        $this->assertSame(3, $row['priority']);
    }

    public function testAMaximumBelowTheMinimumAnswers422(): void
    {
        $this->admin();

        $response = $this->controller()->upsert($this->mainWarehouseId(), $this->municipalityId('boyeros'), $this->dto(min: '5', max: '4'));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('INVALID_COVERAGE', $this->body($response)['error']['code']);
    }

    public function testAZeroRateAsksForConfirmationWithItsKind(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();

        $response = $this->controller()->upsert($w, $this->municipalityId('boyeros'), $this->dto(perKg: '0'));
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $error = $this->body($response)['error'];
        $this->assertSame('COVERAGE_CONFIRMATION_REQUIRED', $error['code']);
        $this->assertSame('zero_rate', $error['confirm']);

        $ok = $this->controller()->upsert($w, $this->municipalityId('boyeros'), $this->dto(perKg: '0', zero: true));
        $this->assertSame(Response::HTTP_OK, $ok->getStatusCode());
    }

    public function testADraftWarehouseAcceptsNoCoverage(): void
    {
        $this->admin();
        $draft = $this->body(self::getContainer()->get(WarehouseController::class)->create(new CreateWarehouseDto('Borrador cobertura')))['id'];

        $response = $this->controller()->upsert($draft, $this->municipalityId('boyeros'), $this->dto());

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('COVERAGE_WAREHOUSE_INVALID', $this->body($response)['error']['code']);
    }

    public function testAnInactiveMunicipalityCannotStartCoverage(): void
    {
        $this->admin();
        $this->em->getConnection()->executeStatement("UPDATE market_municipality SET is_active = FALSE WHERE code = 'cerro'");

        $response = $this->controller()->upsert($this->mainWarehouseId(), $this->municipalityId('cerro'), $this->dto());

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('COVERAGE_MUNICIPALITY_INVALID', $this->body($response)['error']['code']);
    }

    public function testStoppingKeepsTheRowAndStopsServing(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $m = $this->municipalityId('boyeros');
        $this->controller()->upsert($w, $m, $this->dto());

        $stopped = $this->controller()->setActive($w, $m, new SetCoverageActiveDto(false));

        $this->assertSame(Response::HTTP_OK, $stopped->getStatusCode());
        $row = $this->body($this->controller()->list($w))['rows'][0];
        $this->assertFalse($row['active']);
        $this->assertFalse($row['isServing']);
        $this->assertNull($this->body($this->controller()->serving(new Request(query: ['municipality' => (string) $m])))['serving']);
    }

    public function testStoppingAnUnknownCoverageAnswers404(): void
    {
        $this->admin();

        $response = $this->controller()->setActive($this->mainWarehouseId(), $this->municipalityId('boyeros'), new SetCoverageActiveDto(false));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testTheBulkPreviewCountsAndApplyingCreatesTheMunicipalitiesOfTheProvince(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $habana = $this->provinceId('la-habana');
        $this->controller()->upsert($w, $this->municipalityId('boyeros'), $this->dto());

        $preview = $this->body($this->controller()->bulk($w, new BulkCoverageDto($habana, '0.50', '0.60', '3', '15', 2, false, false, false, false)));
        $this->assertSame(['create' => 14, 'update' => 0, 'skipped' => 1, 'applied' => false], array_intersect_key($preview, array_flip(['create', 'update', 'skipped', 'applied'])));
        $this->assertCount(1, $this->body($this->controller()->list($w))['rows']);

        $applied = $this->body($this->controller()->bulk($w, new BulkCoverageDto($habana, '0.50', '0.60', '3', '15', 2, false, false, false, true)));
        $this->assertSame([14, 0, 1, true], [$applied['create'], $applied['update'], $applied['skipped'], $applied['applied']]);
        $rows = $this->body($this->controller()->list($w))['rows'];
        $this->assertCount(15, $rows);
        $boyeros = array_values(array_filter($rows, static fn ($r) => $r['municipality']['name'] === 'Boyeros'))[0];
        $this->assertSame('0.3500', $boyeros['ratePerKg'], 'lo ya cubierto no se sobrescribe sin la opción');

        $again = $this->body($this->controller()->bulk($w, new BulkCoverageDto($habana, '9', '9', '9', '90', 2, false, false, false, true)));
        $this->assertSame([0, 0, 15], [$again['create'], $again['update'], $again['skipped']]);
    }

    public function testTheBulkWithOverwriteUpdatesTheExistingOnes(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();
        $this->controller()->upsert($w, $this->municipalityId('boyeros'), $this->dto());

        $result = $this->body($this->controller()->bulk($w, new BulkCoverageDto($this->provinceId('la-habana'), '0.50', '0.60', '3', '15', 2, true, false, false, true)));

        $this->assertSame([14, 1, 0], [$result['create'], $result['update'], $result['skipped']]);
        $boyeros = array_values(array_filter($this->body($this->controller()->list($w))['rows'], static fn ($r) => $r['municipality']['name'] === 'Boyeros'))[0];
        $this->assertSame('0.5000', $boyeros['ratePerKg']);
    }

    public function testTheCurrencyIsPerWarehouse(): void
    {
        $this->admin();
        $w = $this->mainWarehouseId();

        $response = $this->controller()->setCurrency($w, new SetDeliveryCurrencyDto('usd'));

        $this->assertSame('USD', $this->body($response)['currency']);
        $this->assertSame('USD', $this->body($this->controller()->list($w))['currency']);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->controller()->setCurrency($w, new SetDeliveryCurrencyDto('EURO'))->getStatusCode());
    }

    public function testTwoActiveWarehousesTheLowestPriorityServesAndTheListFlagsTheMoreExpensiveBackup(): void
    {
        $this->admin();
        $main = $this->mainWarehouseId();
        $m = $this->municipalityId('boyeros');
        $other = $this->activeWarehouse('Segundo');
        $this->controller()->upsert($main, $m, $this->dto(perKg: '0.35', priority: 1));
        $this->controller()->upsert($other, $m, $this->dto(perKg: '0.90', priority: 2));

        $serving = $this->body($this->controller()->serving(new Request(query: ['municipality' => (string) $m])))['serving'];
        $this->assertSame($main, $serving['warehouseId']);
        $this->assertFalse($serving['tie']);
        $backup = $this->body($this->controller()->list($other))['rows'][0];
        $this->assertFalse($backup['isServing']);
        $this->assertTrue($backup['backupMoreExpensive']);
    }

    private function activeWarehouse(string $name): int
    {
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO market_warehouse (slug, name, status, is_main, org_unit_id, cost_center_id, municipality_id, created_at, updated_at)
            SELECT :slug, :name, 'active', FALSE, org_unit_id, cost_center_id, municipality_id, NOW(), NOW() FROM market_warehouse WHERE is_main
        SQL, ['slug' => strtolower($name), 'name' => $name]);

        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_warehouse WHERE slug = :s', ['s' => strtolower($name)]);
    }

    public function testADeactivatedWarehouseServesNothingButKeepsItsRows(): void
    {
        $this->admin();
        $other = $this->activeWarehouse('Tercero');
        $m = $this->municipalityId('playa');
        $this->controller()->upsert($other, $m, $this->dto());
        self::getContainer()->get(WarehouseController::class)->deactivate($other);

        $this->assertNull($this->body($this->controller()->serving(new Request(query: ['municipality' => (string) $m])))['serving']);
        $this->assertCount(1, $this->body($this->controller()->list($other))['rows']);
    }
}
