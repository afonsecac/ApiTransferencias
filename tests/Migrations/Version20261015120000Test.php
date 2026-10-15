<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261015120000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261015120000.php';

/**
 * El item de menú «Vista previa del comprador» (COMR-275) cuelga de Mercado › Catálogo y lleva su permiso ROLE_ADMIN. Aditiva,
 * idempotente y reversible.
 *
 * @covers \DoctrineMigrations\Version20261015120000
 */
class Version20261015120000Test extends ProviderFunctionalTestCase
{
    private const TITLE = 'menu.apps.market-buyer-catalog.title';

    protected function setUp(): void
    {
        parent::setUp();

        // Estado de partida: el contenedor de Catálogo, sin el item (la base de pruebas puede traerlo de otras migraciones).
        $connection = $this->em->getConnection();
        $connection->executeStatement("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = '" . self::TITLE . "')");
        $connection->executeStatement("DELETE FROM navigation_item WHERE title = '" . self::TITLE . "'");
        if ($connection->fetchOne("SELECT count(*) FROM navigation_item WHERE title = 'menu.market.catalog.title'") === 0) {
            $connection->executeStatement(<<<'SQL'
                INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
                VALUES (nextval('navigation_item_id_seq'), NULL, 'menu.market.catalog.title', 'collapsable', 'heroicons_outline:squares-2x2', NULL, TRUE, NOW(), NOW(), '00151')
            SQL);
        }
    }

    private function migrate(string $direction = 'up'): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261015120000($connection, new NullLogger());
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(): ?array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT ni.link, ni.order_value, ni.is_active, parent.title AS parent FROM navigation_item ni
             JOIN navigation_item parent ON parent.id = ni.parent_id WHERE ni.title = '" . self::TITLE . "'"
        );

        return $row === false ? null : $row;
    }

    private function permissions(): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            "SELECT count(*) FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id
             WHERE ni.title = '" . self::TITLE . "' AND up.min_role_required = 'ROLE_ADMIN' AND up.is_active"
        );
    }

    public function testUpAddsTheItemUnderCatalogWithItsPermission(): void
    {
        $this->migrate();

        $item = $this->item();
        $this->assertNotNull($item);
        $this->assertSame('/apps/market-buyer-catalog', $item['link']);
        $this->assertSame('menu.market.catalog.title', $item['parent']);
        $this->assertSame('00164', $item['order_value']);
        $this->assertTrue($item['is_active']);
        $this->assertSame(1, $this->permissions());
    }

    public function testItIsIdempotent(): void
    {
        $this->migrate();
        $this->migrate();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM navigation_item WHERE title = '" . self::TITLE . "'"));
        $this->assertSame(1, $this->permissions());
    }

    public function testDownRemovesTheItemAndItsPermission(): void
    {
        $this->migrate();
        $this->migrate('down');

        $this->assertNull($this->item());
        $this->assertSame(0, $this->permissions());
    }

    public function testWithoutTheCatalogContainerItDoesNothing(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement("UPDATE navigation_item SET parent_id = NULL WHERE parent_id IN (SELECT id FROM navigation_item WHERE title = 'menu.market.catalog.title')");
        $connection->executeStatement("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.market.catalog.title')");
        $connection->executeStatement("DELETE FROM navigation_item WHERE title = 'menu.market.catalog.title'");

        $this->migrate();

        $this->assertNull($this->item());
    }
}
