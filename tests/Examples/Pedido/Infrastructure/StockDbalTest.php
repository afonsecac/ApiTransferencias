<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Infrastructure;

use App\Tests\Examples\Pedido\Domain\Cantidad;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\DBAL\Connection;

/**
 * Convención para Infrastructure: FunctionalTestCase contra Postgres real (nunca mocks de la base de datos).
 * Se exige al menos un test así por repositorio y por consumidor de evento.
 */
final class StockDbalTest extends FunctionalTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->em->getConnection();
        // Tabla temporal: vive solo en esta conexión y se descarta sola, no toca el esquema real.
        $this->connection->executeStatement('CREATE TEMP TABLE ejemplo_stock (sku VARCHAR(20) PRIMARY KEY, disponible INT NOT NULL)');
        $this->connection->executeStatement("INSERT INTO ejemplo_stock (sku, disponible) VALUES ('arroz', 5)");
    }

    public function testReservesAtomicallyInTheDatabase(): void
    {
        $stock = new StockDbal($this->connection);

        self::assertTrue($stock->reservar('arroz', new Cantidad(3)));
        self::assertSame(2, $stock->disponible('arroz'));
    }

    public function testNeverLeavesNegativeStock(): void
    {
        $stock = new StockDbal($this->connection);

        self::assertFalse($stock->reservar('arroz', new Cantidad(6)));
        self::assertSame(5, $stock->disponible('arroz'));
    }
}
