<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\Buyer;
use App\Market\Organizacion\Domain\BuyerRegistry;
use App\Market\Organizacion\Domain\OrgUnit;

final class InMemoryBuyerRegistry implements BuyerRegistry
{
    /** @var array<int, Buyer> */
    public array $buyers = [];

    /** @var array<int, int> id de la unidad por id del comprador */
    public array $links = [];

    public function add(Buyer $buyer): void
    {
        $this->buyers[$buyer->clientId] = $buyer;
    }

    public function find(int $clientId): ?Buyer
    {
        return $this->buyers[$clientId] ?? null;
    }

    public function orgUnitIdOf(int $clientId): ?int
    {
        return $this->links[$clientId] ?? null;
    }

    public function link(int $clientId, OrgUnit $unit): void
    {
        $this->links[$clientId] = (int) $unit->id();
    }
}
