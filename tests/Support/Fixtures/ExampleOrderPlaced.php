<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures;

/**
 * Evento de ejemplo con la forma que propone ADR-001: solo identificadores y datos inmutables.
 * Sirve para demostrar el test de contrato; los eventos reales se escriben en cada contexto.
 */
final readonly class ExampleOrderPlaced
{
    /**
     * @param list<array{productId: int, quantity: int}> $lines
     */
    public function __construct(
        public string $eventId,
        public \DateTimeImmutable $occurredAt,
        public int $tenantId,
        public int $orderId,
        public array $lines,
    ) {
    }
}
