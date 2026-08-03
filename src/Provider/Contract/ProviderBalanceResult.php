<?php

namespace App\Provider\Contract;

/**
 * Saldo de plataforma (nuestro saldo con el proveedor), multi-moneda.
 */
final readonly class ProviderBalanceResult
{
    /**
     * @param array<string, float> $amounts Clave = moneda (CUP, USD, ...)
     */
    public function __construct(
        public array $amounts,
        public \DateTimeImmutable $fetchedAt,
    ) {
    }
}
