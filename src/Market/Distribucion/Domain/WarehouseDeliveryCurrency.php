<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

use Doctrine\ORM\Mapping as ORM;

/** La moneda de las tarifas de entrega de un almacén (una sola por almacén). Sin fila, la de por defecto (EUR). */
#[ORM\Entity]
#[ORM\Table(name: 'market_warehouse_delivery_currency')]
class WarehouseDeliveryCurrency
{
    #[ORM\Id]
    #[ORM\Column(name: 'warehouse_id', type: 'integer')]
    private int $warehouseId;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    public function __construct(int $warehouseId, string $currency)
    {
        $this->warehouseId = $warehouseId;
        $this->currency = $currency;
    }

    public function warehouseId(): int
    {
        return $this->warehouseId;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function change(string $currency): void
    {
        $this->currency = $currency;
    }
}
