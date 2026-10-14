<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014210000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014210000.php';

/**
 * COMR-70: tabla de categorías en árbol. El identificador es único, el padre existe (RESTRICT) y una categoría no es su propio
 * padre; la profundidad máxima la valida el dominio, no la base.
 *
 * @covers \DoctrineMigrations\Version20261014210000
 */
class Version20261014210000Test extends ProviderFunctionalTestCase
{
    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014210000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function insert(string $slug, ?int $parentId = null): void
    {
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_category (slug, parent_id, name_es, position, is_active, created_at, updated_at) VALUES (:slug, :p, 'Nombre', 0, TRUE, NOW(), NOW())",
            ['slug' => $slug, 'p' => $parentId],
        );
    }

    public function testRunningItAgainKeepsTheExistingCategories(): void
    {
        $this->insert('ya-existe');

        $this->runMigration();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_category WHERE slug = 'ya-existe'"));
    }

    public function testTheSlugIsUnique(): void
    {
        $this->insert('cat-1');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insert('cat-1');
    }

    public function testTheParentMustExist(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->insert('sin-padre', 987654);
    }

    public function testTheParentCannotBeDeletedWhileItHasChildren(): void
    {
        $this->insert('padre');
        $parent = (int) $this->em->getConnection()->fetchOne("SELECT id FROM market_category WHERE slug = 'padre'");
        $this->insert('hija', $parent);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('fk_market_category_parent');

        $this->em->getConnection()->executeStatement('DELETE FROM market_category WHERE id = :id', ['id' => $parent]);
    }

    public function testACategoryCannotBeItsOwnParent(): void
    {
        $this->insert('propia');

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_category_not_own_parent');

        $this->em->getConnection()->executeStatement("UPDATE market_category SET parent_id = id WHERE slug = 'propia'");
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014210000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
