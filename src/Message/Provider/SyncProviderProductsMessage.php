<?php

namespace App\Message\Provider;

final readonly class SyncProviderProductsMessage
{
    public function __construct(
        public string $providerCode,
        public int $environmentId,
    ) {
    }
}
