<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Evento de ejemplo con la forma que propone ADR-001: solo identificadores y datos inmutables.
 * Sirve para demostrar el test de contrato; los eventos reales se escriben en cada contexto.
 */
final readonly class ExampleOrderPlaced implements DomainEvent
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

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
