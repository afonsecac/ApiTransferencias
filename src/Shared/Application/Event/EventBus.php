<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Puerto de publicación de eventos. La implementación los escribe en la misma transacción que
 * el cambio de negocio (outbox): si la transacción se revierte, los eventos también.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
