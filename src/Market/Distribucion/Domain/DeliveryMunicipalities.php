<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/** Puerto síncrono hacia el catálogo de municipios (contexto Geografía): solo lectura. */
interface DeliveryMunicipalities
{
    public function find(int $id): ?DeliveryMunicipality;

    /**
     * @param list<int> $ids
     *
     * @return array<int, DeliveryMunicipality> por id; faltan los que no existen
     */
    public function findMany(array $ids): array;

    /** @return list<DeliveryMunicipality> los municipios que se pueden elegir de una provincia */
    public function selectableOfProvince(int $provinceId): array;
}
