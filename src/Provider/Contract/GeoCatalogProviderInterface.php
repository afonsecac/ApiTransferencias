<?php

namespace App\Provider\Contract;

interface GeoCatalogProviderInterface extends CommunicationProviderInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function fetchNationalities(ProviderContext $context): iterable;

    /**
     * @return iterable<array<string, mixed>>
     */
    public function fetchProvinces(ProviderContext $context): iterable;

    /**
     * @return iterable<array<string, mixed>>
     */
    public function fetchCommercialOffices(ProviderContext $context, ?int $provinceExternalId = null): iterable;
}
