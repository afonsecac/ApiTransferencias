<?php

namespace App\Service\Provider\Manual;

use App\Entity\CommunicationProduct;

final readonly class ManualProductResult
{
    /**
     * @param list<CommunicationProduct> $products
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public array $products,
    ) {
    }
}
