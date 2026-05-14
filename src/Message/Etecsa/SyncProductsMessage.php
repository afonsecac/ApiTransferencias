<?php

namespace App\Message\Etecsa;

final readonly class SyncProductsMessage
{
    public function __construct(
        public int $environmentId,
    ) {
    }
}
