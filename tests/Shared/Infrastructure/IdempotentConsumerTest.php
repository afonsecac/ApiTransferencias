<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Event\DbalProcessedEvents;
use App\Shared\Infrastructure\Event\IdempotentConsumer;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Fixtures\ExampleOrderPlaced;

final class IdempotentConsumerTest extends FunctionalTestCase
{
    private IdempotentConsumer $consumer;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->em->getConnection();
        $this->consumer = new IdempotentConsumer($connection, new DbalProcessedEvents($connection));
        $connection->executeStatement('CREATE TEMP TABLE ejemplo_efectos (n INT NOT NULL)');
    }

    public function testRunsTheHandlerOnceWhenTheEventIsDeliveredTwice(): void
    {
        $event = $this->event('evt-1');

        $this->consumer->consume('consumidor-a', $event, $this->efecto());
        $this->consumer->consume('consumidor-a', $event, $this->efecto());

        self::assertSame(1, $this->efectos());
    }

    public function testDifferentConsumersEachRunTheirHandler(): void
    {
        $event = $this->event('evt-1');

        $this->consumer->consume('consumidor-a', $event, $this->efecto());
        $this->consumer->consume('consumidor-b', $event, $this->efecto());

        self::assertSame(2, $this->efectos());
    }

    public function testAFailingHandlerLeavesNoTraceSoTheRetryRunsIt(): void
    {
        $event = $this->event('evt-1');

        try {
            $this->consumer->consume('consumidor-a', $event, function (): void {
                $this->efecto()();
                throw new \RuntimeException('falla a mitad');
            });
            self::fail('La excepción del manejador debe propagarse para que Messenger reintente.');
        } catch (\RuntimeException) {
        }

        self::assertSame(0, $this->efectos(), 'El efecto parcial se revierte con la transacción.');

        $this->consumer->consume('consumidor-a', $event, $this->efecto());

        self::assertSame(1, $this->efectos(), 'El reintento sí se ejecuta: el evento no quedó marcado.');
    }

    private function efecto(): \Closure
    {
        return fn () => $this->em->getConnection()->executeStatement('INSERT INTO ejemplo_efectos (n) VALUES (1)');
    }

    private function efectos(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ejemplo_efectos');
    }

    private function event(string $id): ExampleOrderPlaced
    {
        return new ExampleOrderPlaced($id, new \DateTimeImmutable('2026-10-12T10:00:00+00:00'), 1, 7, []);
    }
}
