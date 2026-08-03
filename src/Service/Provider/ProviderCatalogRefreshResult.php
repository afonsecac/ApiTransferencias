<?php

namespace App\Service\Provider;

final readonly class ProviderCatalogRefreshResult
{
    public function __construct(
        public int $pairsProcessed,
        public int $pairsFailed,
        public int $productsChanged,
        public int $pricePackagesUpdated,
    ) {
    }
}
