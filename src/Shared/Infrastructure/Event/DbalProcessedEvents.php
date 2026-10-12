<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Application\Event\ProcessedEvents;
use Doctrine\DBAL\Connection;

final class DbalProcessedEvents implements ProcessedEvents
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function markProcessed(string $consumer, string $eventId): bool
    {
        // Atómico: con dos entregas concurrentes la segunda espera a la primera y no inserta nada.
        $inserted = $this->connection->executeStatement(
            'INSERT INTO processed_event (consumer, event_id, processed_at) VALUES (:consumer, :eventId, NOW()) ON CONFLICT (consumer, event_id) DO NOTHING',
            ['consumer' => $consumer, 'eventId' => $eventId],
        );

        return 1 === $inserted;
    }
}
