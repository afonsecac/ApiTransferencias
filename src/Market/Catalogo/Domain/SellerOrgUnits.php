<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Puerto síncrono hacia las unidades organizativas (contexto Organización): solo lectura.
 */
interface SellerOrgUnits
{
    public function find(int $orgUnitId): ?SellerOrgUnit;

    /**
     * @param list<int> $orgUnitIds
     * @return array<int, SellerOrgUnit> por id de unidad; faltan las que no existen
     */
    public function findMany(array $orgUnitIds): array;
}
