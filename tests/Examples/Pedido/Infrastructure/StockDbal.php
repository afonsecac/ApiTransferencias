<?php

declare(strict_types=1);

namespace App\Tests\Examples\Pedido\Infrastructure;

use App\Tests\Examples\Pedido\Application\StockPort;
use App\Tests\Examples\Pedido\Domain\Cantidad;
use Doctrine\DBAL\Connection;

/**
 * Implementación real del puerto. La reserva es una sola sentencia condicionada (mismo patrón de
 * reclamo atómico que ya usa el repositorio de ventas): dos reservas simultáneas no pueden dejar stock negativo.
 */
final class StockDbal implements StockPort
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function disponible(string $sku): int
    {
        return (int) $this->connection->fetchOne('SELECT disponible FROM ejemplo_stock WHERE sku = :sku', ['sku' => $sku]);
    }

    public function reservar(string $sku, Cantidad $cantidad): bool
    {
        $filas = $this->connection->executeStatement(
            'UPDATE ejemplo_stock SET disponible = disponible - :n WHERE sku = :sku AND disponible >= :n',
            ['n' => $cantidad->valor, 'sku' => $sku],
        );

        return $filas > 0;
    }
}
