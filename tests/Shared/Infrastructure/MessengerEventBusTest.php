<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Application\Event\EventBus;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Fixtures\ExampleOrderPlaced;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * El bus de eventos es un outbox: publicar escribe en la tabla de Messenger dentro de la
 * transacción en curso, así que un rollback descarta también los eventos.
 */
final class MessengerEventBusTest extends FunctionalTestCase
{
    private EventBus $bus;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $bus = self::getContainer()->get(EventBus::class);
        \assert($bus instanceof EventBus);
        $this->bus = $bus;
        $this->connection = $this->em->getConnection();
    }

    public function testPublishWritesTheEventToTheDomainEventsQueue(): void
    {
        $this->bus->publish($this->event('evt-1'));

        self::assertSame(1, $this->queuedEvents());
    }

    public function testPublishesEveryEventInOrder(): void
    {
        $this->bus->publish($this->event('evt-1'), $this->event('evt-2'));

        self::assertSame(2, $this->queuedEvents());
    }

    public function testRollbackDiscardsThePublishedEvents(): void
    {
        $this->connection->beginTransaction();
        $this->bus->publish($this->event('evt-1'));
        $this->connection->rollBack();

        self::assertSame(0, $this->queuedEvents());
    }

    public function testCommitKeepsThePublishedEvents(): void
    {
        $this->connection->beginTransaction();
        $this->bus->publish($this->event('evt-1'));
        $this->connection->commit();

        self::assertSame(1, $this->queuedEvents());
    }

    public function testTheConsumerReceivesTheSameEvent(): void
    {
        $this->bus->publish($this->event('evt-1'));

        $receiver = self::getContainer()->get('messenger.transport.domain_events');
        \assert($receiver instanceof ReceiverInterface);
        $envelopes = iterator_to_array($receiver->get(), false);

        self::assertCount(1, $envelopes);
        $received = $envelopes[0]->getMessage();
        self::assertInstanceOf(ExampleOrderPlaced::class, $received);
        self::assertSame('evt-1', $received->getEventId());
        self::assertSame(7, $received->orderId);
    }

    public function testDoesNotUseTheFailedQueue(): void
    {
        $this->bus->publish($this->event('evt-1'));

        self::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed' AND body LIKE '%evt-1%'"));
    }

    private function event(string $id): ExampleOrderPlaced
    {
        return new ExampleOrderPlaced($id, new \DateTimeImmutable('2026-10-12T10:00:00+00:00'), 1, 7, [['productId' => 3, 'quantity' => 2]]);
    }

    private function queuedEvents(): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'domain_events'");
    }
}
