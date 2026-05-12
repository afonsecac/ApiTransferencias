<?php

namespace App\DTO\Etecsa;

final class EtecsaBalanceDto
{
    public function __construct(
        public readonly float $cupAmount,
        public readonly float $usdAmount,
        public readonly \DateTimeImmutable $fetchedAt,
    ) {
    }
}
