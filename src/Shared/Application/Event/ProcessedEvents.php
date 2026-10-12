<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

interface ProcessedEvents
{
    /**
     * Registra que $consumer procesa $eventId. Devuelve false si ya lo había registrado
     * (entrega duplicada) y el consumidor debe omitirlo.
     */
    public function markProcessed(string $consumer, string $eventId): bool;
}
