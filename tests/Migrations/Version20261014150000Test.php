<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014150000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014150000.php';

/**
 * COMR-272: cada Client existente queda referenciado a una unidad organizativa de tipo comprador con un centro
 * de costo principal que lleva su nombre. Idempotente: ejecutarla dos veces no duplica unidades ni centros.
 *
 * @covers \DoctrineMigrations\Version20261014150000
 */
class Version20261014150000Test extends ProviderFunctionalTestCase
{
    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014150000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function newClient(string $name, string $country, string $identification, string $type = 'CIF', ?string $address = 'Calle 1'): int
    {
        static $n = 0;
        ++$n;
        $client = (new \App\Entity\Client())
            ->setCompanyName($name)
            ->setCompanyCountry($country)
            ->setCompanyAddress($address)
            ->setCompanyEmail("mig{$n}@example.test")
            ->setCompanyPhoneNumber('555000' . $n)
            ->setCompanyIdentification($identification)
            ->setCompanyIdentificationType($type)
            ->setDiscountOfClient(0.0)
            ->setIsActive(true);
        $this->em->persist($client);
        $this->em->flush();

        return (int) $client->getId();
    }

    /**
     * @return array<string, mixed>|false
     */
    private function unitOf(int $clientId): array|false
    {
        return $this->em->getConnection()->fetchAssociative(
            'SELECT u.* FROM market_org_unit u JOIN client c ON c.org_unit_id = u.id WHERE c.id = :id',
            ['id' => $clientId],
        );
    }

    private function rows(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM {$table}");
    }

    public function testEveryClientEndsUpWithABuyerUnit(): void
    {
        $id = $this->newClient('AWS Malta Ltd', 'MLT', ' c63841 ');

        $this->runMigration();

        $unit = $this->unitOf($id);
        $this->assertNotFalse($unit);
        $this->assertSame('MT', $unit['country_code']);
        $this->assertSame('C63841', $unit['tax_id']);
        $this->assertSame('AWS Malta Ltd', $unit['name']);
        $this->assertSame(['buyer'], json_decode($unit['types'], true));
        $this->assertSame([], json_decode($unit['fiscal_data'], true));
        $this->assertSame('Calle 1', $unit['address']);
    }

    public function testTheUnitGetsOnePrincipalActiveCostCenterNamedAfterTheClient(): void
    {
        $id = $this->newClient('AWS Malta Ltd', 'MLT', 'C63841');

        $this->runMigration();

        $centers = $this->em->getConnection()->fetchAllAssociative(
            'SELECT cc.* FROM market_cost_center cc JOIN client c ON c.org_unit_id = cc.org_unit_id WHERE c.id = :id',
            ['id' => $id],
        );
        $this->assertCount(1, $centers);
        $this->assertSame('PRINCIPAL', $centers[0]['code']);
        $this->assertSame('AWS Malta Ltd', $centers[0]['name']);
        $this->assertTrue((bool) $centers[0]['principal']);
        $this->assertTrue((bool) $centers[0]['active']);
    }

    public function testRunningItTwiceDoesNotDuplicateUnitsNorCostCenters(): void
    {
        $this->newClient('AWS Malta Ltd', 'MLT', 'C63841');
        $this->runMigration();
        $units = $this->rows('market_org_unit');
        $centers = $this->rows('market_cost_center');

        $this->runMigration();

        $this->assertSame($units, $this->rows('market_org_unit'));
        $this->assertSame($centers, $this->rows('market_cost_center'));
    }

    public function testTwoClientsWithTheSameCountryAndTaxIdShareOneUnit(): void
    {
        $a = $this->newClient('Comremit Solutions SL', 'ESP', 'B10583565', 'CIF');
        $b = $this->newClient('Comremit Solutions SL (NIF)', 'ESP', 'b10583565', 'NIF');

        $this->runMigration();

        $this->assertSame($this->unitOf($a)['id'], $this->unitOf($b)['id']);
        $centers = $this->em->getConnection()->fetchOne(
            'SELECT count(*) FROM market_cost_center WHERE org_unit_id = :u',
            ['u' => $this->unitOf($a)['id']],
        );
        $this->assertSame(1, (int) $centers);
    }

    public function testItReusesAnExistingUnitAndAddsTheBuyerTypeWithoutLosingTheOthers(): void
    {
        $id = $this->newClient('Alisney AG Cuba', 'CUB', 'B10583565');
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO market_org_unit (name, legal_name, country_code, tax_id, address, billing_email, types, fiscal_data, created_at, updated_at)
            VALUES ('Alisney', 'Alisney AG', 'CU', 'B10583565', 'La Habana', 'a@alisney.example', '["seller"]', '{}', NOW(), NOW())
        SQL);
        $units = $this->rows('market_org_unit');

        $this->runMigration();

        $this->assertSame($units, $this->rows('market_org_unit'));
        $types = json_decode($this->unitOf($id)['types'], true);
        sort($types);
        $this->assertSame(['buyer', 'seller'], $types);
    }

    public function testAClientWithoutAddressGetsAPlaceholder(): void
    {
        $id = $this->newClient('Sin Dirección SA', 'ESP', 'A00000001', address: null);

        $this->runMigration();

        $this->assertSame('Sin dirección registrada', $this->unitOf($id)['address']);
    }

    public function testItFailsLoudlyIfAClientHasACountryItDoesNotRecognise(): void
    {
        $this->newClient('Cliente Raro', 'STJ', 'X1');

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('STJ');

        $this->runMigration();
    }

    public function testItHasADescriptionAndDocumentsHowToRevertIt(): void
    {
        $migration = new Version20261014150000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
