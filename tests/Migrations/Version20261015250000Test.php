<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261015250000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261015250000.php';

/**
 * COMR-281: la pantalla «Unidades de medida» entra en el menú del mercado, dentro de «Catálogo», con permiso de administrador.
 *
 * @covers \DoctrineMigrations\Version20261015250000
 */
class Version20261015250000Test extends ProviderFunctionalTestCase
{
    private const TITLE = 'menu.apps.market-measure-settings.title';

    private function migrate(string $direction): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261015250000($connection, new NullLogger());
        $direction === 'up' ? $migration->up(new Schema()) : $migration->down(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function clear(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = ?)', [self::TITLE]);
        $this->em->getConnection()->executeStatement('DELETE FROM navigation_item WHERE title = ?', [self::TITLE]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(): ?array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT item.link, item.is_active, parent.title AS parent_title FROM navigation_item item LEFT JOIN navigation_item parent ON parent.id = item.parent_id WHERE item.title = ?',
            [self::TITLE],
        );

        return $row === false ? null : $row;
    }

    public function testItAddsTheScreenUnderTheMarketCatalogWithAnAdminPermission(): void
    {
        $this->clear();

        $this->migrate('up');

        $item = $this->item();
        $this->assertSame('/apps/market-measure-settings', $item['link']);
        $this->assertSame('menu.market.catalog.title', $item['parent_title']);
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = ? AND up.min_role_required = 'ROLE_ADMIN'",
            [self::TITLE],
        ));
    }

    public function testRunningItAgainDoesNotDuplicateTheItem(): void
    {
        $this->clear();
        $this->migrate('up');

        $this->migrate('up');

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM navigation_item WHERE title = ?', [self::TITLE]));
    }

    public function testDownRemovesTheItemAndItsPermissions(): void
    {
        $this->clear();
        $this->migrate('up');

        $this->migrate('down');

        $this->assertNull($this->item());
    }
}
