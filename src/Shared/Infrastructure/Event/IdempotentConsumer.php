<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Application\Event\ProcessedEvents;
use App\Shared\Domain\Event\DomainEvent;
use Doctrine\DBAL\Connection;

/**
 * Ejecuta el manejador de un evento una sola vez por consumidor. La marca y los efectos del
 * manejador van en la misma transacción: si el manejador falla no queda marca, y el reintento
 * de Messenger lo vuelve a ejecutar.
 */
final class IdempotentConsumer
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProcessedEvents $processedEvents,
    ) {
    }

    /**
     * @param callable(DomainEvent): mixed $handler
     */
    public function consume(string $consumer, DomainEvent $event, callable $handler): void
    {
        $this->connection->transactional(function () use ($consumer, $event, $handler): void {
            if (!$this->processedEvents->markProcessed($consumer, $event->getEventId())) {
                return;
            }

            $handler($event);
        });
    }
}
