<?php

namespace App\Message\Etecsa;

final readonly class SyncCatalogsMessage
{
    public function __construct(
        public int $environmentId,
    ) {
    }
}
