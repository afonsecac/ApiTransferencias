<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\Market\Inventario\Domain\StockItemType;
use App\Market\Inventario\Domain\StockLedger;
use App\Market\Inventario\Domain\StockLine;
use App\Market\Inventario\Domain\StockMovement;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineStockLedger implements StockLedger
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $warehouseId, StockItemType $type, int $itemId): ?StockLine
    {
        return $this->em->getRepository(StockLine::class)->findOneBy(['warehouseId' => $warehouseId, 'itemType' => $type, 'itemId' => $itemId]);
    }

    public function lines(?int $warehouseId, ?StockItemType $type): array
    {
        $criteria = array_filter(['warehouseId' => $warehouseId, 'itemType' => $type], static fn ($v) => $v !== null);

        /** @var list<StockLine> $lines */
        $lines = $this->em->getRepository(StockLine::class)->findBy($criteria, ['id' => 'ASC']);

        return $lines;
    }

    public function movements(?int $warehouseId, ?int $itemId, int $limit): array
    {
        $criteria = array_filter(['warehouseId' => $warehouseId, 'itemId' => $itemId], static fn ($v) => $v !== null);

        /** @var list<StockMovement> $movements */
        $movements = $this->em->getRepository(StockMovement::class)->findBy($criteria, ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);

        return $movements;
    }

    public function save(StockLine $line, ?StockMovement $movement = null): void
    {
        // Línea y movimiento van en el mismo flush: o se guardan los dos o ninguno.
        $this->em->persist($line);
        if ($movement !== null) {
            $this->em->persist($movement);
        }
        $this->em->flush();
    }
}
