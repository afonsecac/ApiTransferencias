<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016110000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016110000.php';

/**
 * COMR-285: tabla de almacenes, clave de configuración y siembra del almacén principal.
 *
 * @covers \DoctrineMigrations\Version20261016110000
 */
class Version20261016110000Test extends ProviderFunctionalTestCase
{
    private function runUp(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016110000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    public function testTheMainWarehouseCollidesWithNothingWhenItRunsTwice(): void
    {
        $connection = $this->em->getConnection();

        $this->runUp();

        $this->assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM market_warehouse WHERE is_main'));
        $this->assertSame('boyeros', $connection->fetchOne("SELECT property_value FROM sys_config WHERE property_name = 'market.warehouse.default_municipality'"));
    }

    public function testTheMainWarehouseHangsFromThePlatformUnitAndItsPrincipalCostCenter(): void
    {
        $row = $this->em->getConnection()->fetchAssociative(<<<'SQL'
            SELECT w.org_unit_id, w.cost_center_id, s.org_unit_id AS platform_unit, c.principal, w.status, m.code
            FROM market_warehouse w
            JOIN market_seller s ON s.is_platform
            JOIN market_cost_center c ON c.id = w.cost_center_id
            JOIN market_municipality m ON m.id = w.municipality_id
            WHERE w.is_main
        SQL);

        $this->assertSame($row['platform_unit'], $row['org_unit_id']);
        $this->assertTrue((bool) $row['principal']);
        $this->assertSame('active', $row['status']);
        $this->assertSame('boyeros', $row['code']);
    }

    public function testTheDatabaseKeepsASingleMainWarehouseThatIsAlwaysActive(): void
    {
        $connection = $this->em->getConnection();

        $this->expectException(DbalException::class);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO market_warehouse (slug, name, status, is_main, created_at, updated_at)
            VALUES ('otro-principal', 'Otro', 'active', TRUE, NOW(), NOW())
        SQL);
    }

    public function testItFailsLoudlyWhenTheConfiguredMunicipalityDoesNotExist(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM market_warehouse WHERE is_main');
        $connection->executeStatement("UPDATE sys_config SET property_value = 'no-existe' WHERE property_name = 'market.warehouse.default_municipality'");

        $this->expectException(DbalException::class);
        $this->runUp();
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016110000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
