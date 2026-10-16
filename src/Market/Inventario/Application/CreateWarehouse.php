<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\InvalidWarehouse;
use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseAlreadyExists;
use App\Market\Inventario\Domain\WarehouseRepository;
use App\Shared\Domain\InvalidSlug;
use App\Shared\Domain\Slug;

final class CreateWarehouse
{
    public function __construct(private readonly WarehouseRepository $warehouses)
    {
    }

    /**
     * Nace en borrador con solo nombre e identificador.
     *
     * @param string|null $slug por defecto, derivado del nombre (con -2, -3… si ya está en uso)
     *
     * @throws InvalidWarehouse
     * @throws InvalidSlug
     * @throws WarehouseAlreadyExists
     */
    public function __invoke(string $name, ?string $slug = null): WarehouseDetails
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidWarehouse('El almacén necesita un nombre.');
        }
        $slugValue = $slug !== null && trim($slug) !== ''
            ? new Slug(trim($slug))
            : Slug::free($name, fn (string $candidate): bool => $this->warehouses->findBySlug($candidate) !== null);
        if ($this->warehouses->findBySlug($slugValue->value()) !== null) {
            throw WarehouseAlreadyExists::forSlug($slugValue->value());
        }

        $warehouse = Warehouse::draft($slugValue, $name);
        $this->warehouses->save($warehouse);

        return new WarehouseDetails($warehouse, null, null);
    }
}
