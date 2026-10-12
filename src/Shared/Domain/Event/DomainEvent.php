<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Hecho ya ocurrido en un contexto (ADR-001). Solo lleva identificadores y datos inmutables.
 * Los consumidores deduplican por getEventId(): el mismo evento puede entregarse más de una vez.
 */
interface DomainEvent
{
    public function getEventId(): string;

    public function getOccurredAt(): \DateTimeImmutable;
}
