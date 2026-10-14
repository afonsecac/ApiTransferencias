<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014200000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014200000.php';

/**
 * COMR-70: tabla de marcas. La marca pertenece a un vendedor (referenciado, RESTRICT) y su identificador es único.
 *
 * @covers \DoctrineMigrations\Version20261014200000
 */
class Version20261014200000Test extends ProviderFunctionalTestCase
{
    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014200000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function insert(string $slug, ?int $sellerId = null): void
    {
        $sellerId ??= (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_brand (seller_id, slug, name, is_active, created_at, updated_at) VALUES (:s, :slug, 'Marca', TRUE, NOW(), NOW())",
            ['s' => $sellerId, 'slug' => $slug],
        );
    }

    public function testRunningItAgainKeepsTheExistingBrands(): void
    {
        $this->insert('ya-existe');

        $this->runMigration();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_brand WHERE slug = 'ya-existe'"));
    }

    public function testTheSlugIsUnique(): void
    {
        $this->insert('marca-1');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insert('marca-1');
    }

    public function testTheSellerMustExist(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->insert('sin-vendedor', 987654);
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014200000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
