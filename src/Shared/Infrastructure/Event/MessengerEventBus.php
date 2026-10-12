<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Event\DomainEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Los eventos se enrutan al transporte Doctrine `domain_events`, que comparte la conexión
 * (y por tanto la transacción) del código que publica: es el outbox.
 */
final class MessengerEventBus implements EventBus
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->messageBus->dispatch($event);
        }
    }
}
