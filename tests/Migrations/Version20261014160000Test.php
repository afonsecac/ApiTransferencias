<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014160000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014160000.php';

/**
 * COMR-103: la migración siembra el vendedor de plataforma sobre su unidad organizativa. Cada caso parte de una base
 * sin vendedor de plataforma (se borra dentro de la transacción del test) y comprueba qué unidad elige.
 *
 * @covers \DoctrineMigrations\Version20261014160000
 */
class Version20261014160000Test extends ProviderFunctionalTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->em->getConnection();

        // Base limpia de plataforma: sin vendedor, sin unidad con el tipo «platform» y sin la unidad de Comremit.
        $this->db->executeStatement('DELETE FROM market_warehouse');
        $this->db->executeStatement('DELETE FROM market_seller');
        $this->db->executeStatement("UPDATE market_org_unit SET types = '[\"buyer\"]'::json WHERE types::jsonb @> '\"platform\"'::jsonb");
        $this->db->executeStatement("UPDATE client SET org_unit_id = NULL WHERE org_unit_id IN (SELECT id FROM market_org_unit WHERE tax_id IN ('B10583565', 'PLATAFORMA'))");
        $this->db->executeStatement("DELETE FROM market_cost_center WHERE org_unit_id IN (SELECT id FROM market_org_unit WHERE tax_id IN ('B10583565', 'PLATAFORMA'))");
        $this->db->executeStatement("DELETE FROM market_org_unit WHERE tax_id IN ('B10583565', 'PLATAFORMA')");
    }

    private function runMigration(): void
    {
        $migration = new Version20261014160000($this->db, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $this->db->executeStatement($query->getStatement());
        }
    }

    /**
     * @param list<string> $types
     */
    private function insertUnit(string $taxId, array $types, bool $withCenter = true, string $country = 'ES'): int
    {
        $this->db->executeStatement(
            "INSERT INTO market_org_unit (name, legal_name, country_code, tax_id, address, billing_email, types, fiscal_data, created_at, updated_at) VALUES ('Unidad {$taxId}', 'Unidad {$taxId}', :c, :t, 'Calle 1', 'a@b.example', :types, '{}', NOW(), NOW())",
            ['c' => $country, 't' => $taxId, 'types' => json_encode($types)],
        );
        $id = (int) $this->db->fetchOne('SELECT id FROM market_org_unit WHERE country_code = :c AND tax_id = :t', ['c' => $country, 't' => $taxId]);
        if ($withCenter) {
            $this->db->executeStatement("INSERT INTO market_cost_center (org_unit_id, code, name, principal, active, created_at) VALUES (:u, 'PRINCIPAL', 'Principal', TRUE, TRUE, NOW())", ['u' => $id]);
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function platform(): array
    {
        return $this->db->fetchAssociative('SELECT s.*, u.tax_id, u.types AS unit_types FROM market_seller s JOIN market_org_unit u ON u.id = s.org_unit_id WHERE s.is_platform') ?: [];
    }

    /**
     * @return list<string>
     */
    private function sortedTypes(string $json): array
    {
        $types = json_decode($json, true);
        sort($types);

        return $types;
    }

    public function testItReusesTheUnitOfComremitAndAddsTheTypesWithoutLosingTheOthers(): void
    {
        $unit = $this->insertUnit('B10583565', ['buyer']);

        $this->runMigration();

        $platform = $this->platform();
        $this->assertSame($unit, (int) $platform['org_unit_id']);
        $this->assertSame('plataforma', $platform['slug']);
        $this->assertTrue((bool) $platform['is_active']);
        $this->assertSame(['buyer', 'platform', 'seller'], $this->sortedTypes($platform['unit_types']));
        $principal = (int) $this->db->fetchOne('SELECT id FROM market_cost_center WHERE org_unit_id = :u AND principal', ['u' => $unit]);
        $this->assertSame($principal, (int) $platform['cost_center_id']);
    }

    public function testAUnitThatAlreadyHasThePlatformTypeWinsOverComremit(): void
    {
        $this->insertUnit('B10583565', ['buyer']);
        $explicit = $this->insertUnit('PLAT-1', ['platform']);

        $this->runMigration();

        $this->assertSame($explicit, (int) $this->platform()['org_unit_id']);
    }

    public function testWithoutAnyCandidateItCreatesAnEditableProvisionalUnit(): void
    {
        $this->runMigration();

        $platform = $this->platform();
        $this->assertSame('PLATAFORMA', $platform['tax_id']);
        $this->assertSame(['platform', 'seller'], $this->sortedTypes($platform['unit_types']));
        $center = $this->db->fetchAssociative('SELECT * FROM market_cost_center WHERE id = :id', ['id' => $platform['cost_center_id']]);
        $this->assertSame('PRINCIPAL', $center['code']);
        $this->assertTrue((bool) $center['principal']);
    }

    public function testAUnitWithoutAPrincipalCostCenterGetsOne(): void
    {
        $unit = $this->insertUnit('PLAT-2', ['platform'], withCenter: false);

        $this->runMigration();

        $platform = $this->platform();
        $this->assertSame($unit, (int) $platform['org_unit_id']);
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT count(*) FROM market_cost_center WHERE org_unit_id = :u AND principal AND active', ['u' => $unit]));
    }

    public function testRunningItTwiceDoesNotDuplicateTheSellerTheUnitNorTheCostCenter(): void
    {
        $this->runMigration();
        $units = (int) $this->db->fetchOne('SELECT count(*) FROM market_org_unit');
        $centers = (int) $this->db->fetchOne('SELECT count(*) FROM market_cost_center');

        $this->runMigration();

        $this->assertSame(1, (int) $this->db->fetchOne('SELECT count(*) FROM market_seller WHERE is_platform'));
        $this->assertSame($units, (int) $this->db->fetchOne('SELECT count(*) FROM market_org_unit'));
        $this->assertSame($centers, (int) $this->db->fetchOne('SELECT count(*) FROM market_cost_center'));
    }

    public function testAnExistingPlatformSellerIsLeftAsItIs(): void
    {
        $this->runMigration();
        $this->db->executeStatement("UPDATE market_seller SET name = 'Renombrado a mano' WHERE is_platform");

        $this->runMigration();

        $this->assertSame('Renombrado a mano', $this->platform()['name']);
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014160000($this->db, new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
