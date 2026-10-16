<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\MainWarehouseProtected;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Market\Inventario\Domain\WarehouseStatus;
use App\Market\Inventario\Domain\WarehouseTransitionInvalid;

final class DiscardWarehouse
{
    public function __construct(private readonly WarehouseRepository $warehouses)
    {
    }

    /**
     * Es el único caso en que se borra un almacén: un borrador que nunca fue activo.
     *
     * @throws WarehouseNotFound
     * @throws WarehouseTransitionInvalid
     */
    public function __invoke(int $id): void
    {
        $warehouse = $this->warehouses->findById($id) ?? throw WarehouseNotFound::forId($id);
        if (!$warehouse->canBeDiscarded()) {
            throw $warehouse->isMain()
                ? WarehouseTransitionInvalid::between($warehouse->status(), 'descartar (es el almacén principal)')
                : WarehouseTransitionInvalid::between($warehouse->status(), 'descartar: solo se descarta un borrador');
        }
        $this->warehouses->remove($warehouse);
    }
}
