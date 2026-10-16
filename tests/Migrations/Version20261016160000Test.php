<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016160000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016160000.php';

/**
 * COMR-287: el vínculo usuario–almacén; la base refuerza la unicidad y que haya al menos un permiso.
 *
 * @covers \DoctrineMigrations\Version20261016160000
 */
class Version20261016160000Test extends ProviderFunctionalTestCase
{
    private function link(int $user, bool $stock = true): void
    {
        $this->em->getConnection()->executeStatement(
            'INSERT INTO market_warehouse_user (user_id, warehouse_id, perm_stock, created_at, updated_at) VALUES (:u, (SELECT id FROM market_warehouse WHERE is_main), :s, NOW(), NOW())',
            ['u' => $user, 's' => $stock ? 1 : 0],
            ['s' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
    }

    public function testItRejectsTheSameUserTwiceInTheSameWarehouse(): void
    {
        $user = (int) $this->createAdminUser()->getId();
        $this->link($user);

        $this->expectException(DbalException::class);
        $this->link($user);
    }

    public function testItRejectsALinkWithoutAnyPermission(): void
    {
        $user = (int) $this->createAdminUser()->getId();

        $this->expectException(DbalException::class);
        $this->link($user, false);
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016160000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }

        $this->assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM market_warehouse_user'));
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016160000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
