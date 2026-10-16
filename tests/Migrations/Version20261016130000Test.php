<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016130000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016130000.php';

/**
 * COMR-72: tablas de cobertura y de moneda por almacén; la base refuerza lo que el dominio ya valida.
 *
 * @covers \DoctrineMigrations\Version20261016130000
 */
class Version20261016130000Test extends ProviderFunctionalTestCase
{
    private function insert(int $warehouse, int $municipality, string $perKg = '0.35', string $min = '2', ?string $max = '12', int $priority = 1): void
    {
        $this->em->getConnection()->executeStatement(
            'INSERT INTO market_delivery_coverage (warehouse_id, municipality_id, rate_per_kg, rate_per_l, min_price, max_price, priority, created_at, updated_at)
             VALUES (:w, :m, :kg, 0.4, :min, :max, :p, NOW(), NOW())',
            ['w' => $warehouse, 'm' => $municipality, 'kg' => $perKg, 'min' => $min, 'max' => $max, 'p' => $priority],
        );
    }

    private function warehouse(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_warehouse WHERE is_main');
    }

    private function municipality(string $code): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_municipality WHERE code = :c', ['c' => $code]);
    }

    public function testItAcceptsAValidRowAndRejectsADuplicatedMunicipality(): void
    {
        $this->insert($this->warehouse(), $this->municipality('boyeros'));
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_delivery_coverage'));

        $this->expectException(DbalException::class);
        $this->insert($this->warehouse(), $this->municipality('boyeros'));
    }

    public function testItRejectsAMaximumBelowTheMinimum(): void
    {
        $this->expectException(DbalException::class);
        $this->insert($this->warehouse(), $this->municipality('boyeros'), min: '5', max: '4');
    }

    public function testItRejectsANegativeRate(): void
    {
        $this->expectException(DbalException::class);
        $this->insert($this->warehouse(), $this->municipality('boyeros'), perKg: '-1');
    }

    public function testItRejectsAPriorityOutOfRange(): void
    {
        $this->expectException(DbalException::class);
        $this->insert($this->warehouse(), $this->municipality('boyeros'), priority: 0);
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016130000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }

        $this->assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM market_delivery_coverage'));
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016130000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
