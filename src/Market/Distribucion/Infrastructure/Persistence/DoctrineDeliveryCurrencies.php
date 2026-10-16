<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Persistence;

use App\Market\Distribucion\Domain\DeliveryCurrencies;
use App\Market\Distribucion\Domain\WarehouseDeliveryCurrency;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDeliveryCurrencies implements DeliveryCurrencies
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function currencyOf(int $warehouseId): string
    {
        return $this->em->find(WarehouseDeliveryCurrency::class, $warehouseId)?->currency() ?? self::DEFAULT;
    }

    public function set(int $warehouseId, string $currency): void
    {
        $row = $this->em->find(WarehouseDeliveryCurrency::class, $warehouseId);
        if ($row === null) {
            $this->em->persist(new WarehouseDeliveryCurrency($warehouseId, $currency));
        } else {
            $row->change($currency);
        }
        $this->em->flush();
    }
}
