<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261015230000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261015230000.php';

/**
 * COMR-281: las cantidades pasan de gramos y mililitros a kilogramos y litros, con 6 decimales. Cada prueba deja la tabla en
 * el estado anterior (columnas en g y ml, 3 decimales), siembra productos y comprueba qué convierte la migración: solo los
 * pesos y los volúmenes, nunca los productos por unidades. Las pruebas se deshacen al final (DDL transaccional).
 *
 * @covers \DoctrineMigrations\Version20261015230000
 */
class Version20261015230000Test extends ProviderFunctionalTestCase
{
    private function migrate(string $direction): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261015230000($connection, new NullLogger());
        $direction === 'up' ? $migration->up(new Schema()) : $migration->down(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    /** Devuelve la tabla a como estaba antes de la migración: columnas en g y ml con 3 decimales. */
    protected function setUp(): void
    {
        parent::setUp();
        $c = $this->em->getConnection();
        $c->executeStatement('ALTER TABLE market_product RENAME COLUMN total_weight_base TO total_weight_g');
        $c->executeStatement('ALTER TABLE market_product RENAME COLUMN total_volume_base TO total_volume_ml');
        foreach (['content_base', 'unit_weight_base', 'unit_volume_base'] as $column) {
            $c->executeStatement("ALTER TABLE market_product ALTER COLUMN $column TYPE NUMERIC(14,3)");
        }
        $c->executeStatement('ALTER TABLE market_product ALTER COLUMN total_weight_g TYPE NUMERIC(16,3)');
        $c->executeStatement('ALTER TABLE market_product ALTER COLUMN total_volume_ml TYPE NUMERIC(16,3)');
    }

    private function sellerId(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function insert(string $sku, array $columns): void
    {
        $columns = $columns + ['sku' => $sku, 'seller_id' => $this->sellerId(), 'status' => 'draft', 'service' => 'Gift Cards', 'subservice' => 'Retail', 'texts' => '{}'];
        $names = implode(', ', array_keys($columns));
        $params = implode(', ', array_map(static fn (string $c) => ':' . $c, array_keys($columns)));
        $this->em->getConnection()->executeStatement("INSERT INTO market_product ($names, created_at, updated_at) VALUES ($params, NOW(), NOW())", $columns);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $sku): array
    {
        return $this->em->getConnection()->fetchAssociative(
            'SELECT content_base, unit_weight_base, unit_volume_base, total_weight_base, total_volume_base FROM market_product WHERE sku = ?',
            [$sku],
        );
    }

    private function seed(): void
    {
        // 2,5 lb de arroz: 1133.981 g, total 1133.981 g
        $this->insert('M-PESO', ['content_base' => 1133.981, 'content_original' => 2.5, 'content_unit' => 'lb', 'delivery_kind' => 'weight', 'delivery_unit' => 'kg', 'total_weight_g' => 1133.981]);
        // 330 ml: base 330, total 330 ml
        $this->insert('M-VOL', ['content_base' => 330, 'content_original' => 330, 'content_unit' => 'ml', 'delivery_kind' => 'volume', 'delivery_unit' => 'l', 'total_volume_ml' => 330]);
        // 12 unidades de 330 g y 0,1 l: la base del contenido son unidades y no se toca; las referencias y los totales sí
        $this->insert('M-CONTEO', [
            'content_base' => 12, 'content_original' => 12, 'content_unit' => 'unit',
            'unit_weight_base' => 330, 'unit_weight_original' => 330, 'unit_weight_unit' => 'g',
            'unit_volume_base' => 100, 'unit_volume_original' => 100, 'unit_volume_unit' => 'ml',
            'delivery_kind' => 'weight', 'delivery_unit' => 'kg', 'total_weight_g' => 3960, 'total_volume_ml' => 1200,
        ]);
        // Un producto digital, sin nada físico
        $this->insert('M-VALOR', ['value_currency' => 'USD', 'value_min_minor' => 2500]);
    }

    public function testItConvertsWeightsAndVolumesToKilogramsAndLitersButNeverCounts(): void
    {
        $this->seed();

        $this->migrate('up');

        $weight = $this->row('M-PESO');
        $this->assertEqualsWithDelta(1.133981, (float) $weight['content_base'], 0.0000001);
        $this->assertEqualsWithDelta(1.133981, (float) $weight['total_weight_base'], 0.0000001);
        $volume = $this->row('M-VOL');
        $this->assertEqualsWithDelta(0.33, (float) $volume['content_base'], 0.0000001);
        $this->assertEqualsWithDelta(0.33, (float) $volume['total_volume_base'], 0.0000001);
        $count = $this->row('M-CONTEO');
        $this->assertEqualsWithDelta(12.0, (float) $count['content_base'], 0.0000001, 'Las unidades no se convierten.');
        $this->assertEqualsWithDelta(0.33, (float) $count['unit_weight_base'], 0.0000001);
        $this->assertEqualsWithDelta(0.1, (float) $count['unit_volume_base'], 0.0000001);
        $this->assertEqualsWithDelta(3.96, (float) $count['total_weight_base'], 0.0000001);
        $this->assertEqualsWithDelta(1.2, (float) $count['total_volume_base'], 0.0000001);
        $digital = $this->row('M-VALOR');
        $this->assertNull($digital['content_base']);
        $this->assertNull($digital['total_weight_base']);
    }

    public function testItKeepsTheDecimalsThatGramsCouldNotHold(): void
    {
        $this->seed();
        $this->migrate('up');

        $this->insert('M-MINI', ['content_base' => 0.0025, 'content_original' => 2.5, 'content_unit' => 'g', 'delivery_kind' => 'weight', 'delivery_unit' => 'g', 'total_weight_base' => 0.0025]);

        $this->assertEqualsWithDelta(0.0025, (float) $this->row('M-MINI')['content_base'], 0.0000001);
    }

    public function testRunningItAgainDoesNotConvertTwice(): void
    {
        $this->seed();
        $this->migrate('up');

        $this->migrate('up');

        $this->assertEqualsWithDelta(1.133981, (float) $this->row('M-PESO')['content_base'], 0.0000001);
        $this->assertEqualsWithDelta(0.33, (float) $this->row('M-CONTEO')['unit_weight_base'], 0.0000001);
        $this->assertEqualsWithDelta(3.96, (float) $this->row('M-CONTEO')['total_weight_base'], 0.0000001);
    }

    public function testDownGoesBackToGramsAndMilliliters(): void
    {
        $this->seed();
        $this->migrate('up');

        $this->migrate('down');

        $names = $this->em->getConnection()->fetchFirstColumn("SELECT column_name FROM information_schema.columns WHERE table_name = 'market_product' AND column_name IN ('total_weight_g', 'total_volume_ml', 'total_weight_base')");
        sort($names);
        $this->assertSame(['total_volume_ml', 'total_weight_g'], $names);
        $row = $this->em->getConnection()->fetchAssociative("SELECT content_base, unit_weight_base, total_weight_g FROM market_product WHERE sku = 'M-CONTEO'");
        $this->assertEqualsWithDelta(12.0, (float) $row['content_base'], 0.0001);
        $this->assertEqualsWithDelta(330.0, (float) $row['unit_weight_base'], 0.0001);
        $this->assertEqualsWithDelta(3960.0, (float) $row['total_weight_g'], 0.0001);
    }
}
