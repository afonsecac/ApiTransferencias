<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Puerto hacia los compradores existentes (los `Client`): leerlos y dejarlos referenciados a su unidad.
 */
interface BuyerRegistry
{
    public function find(int $clientId): ?Buyer;

    public function orgUnitIdOf(int $clientId): ?int;

    /**
     * @param OrgUnit $unit ya guardada (con id)
     */
    public function link(int $clientId, OrgUnit $unit): void;
}
