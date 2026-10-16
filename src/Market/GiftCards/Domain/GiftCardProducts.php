<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

/** Puerto síncrono hacia los productos (Catálogo) y los centros de costo de su vendedor (Organización): solo lectura. */
interface GiftCardProducts
{
    public function find(int $id): ?GiftCardProduct;

    /**
     * @param list<int> $ids
     *
     * @return array<int, GiftCardProduct> por id
     */
    public function findMany(array $ids): array;
}
