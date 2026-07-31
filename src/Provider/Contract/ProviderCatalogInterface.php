<?php

namespace App\Provider\Contract;

interface ProviderCatalogInterface extends CommunicationProviderInterface
{
    /**
     * @return iterable<ProviderProductDto>
     */
    public function fetchProducts(ProviderContext $context): iterable;
}
