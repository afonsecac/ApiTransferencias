<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Event\DbalProcessedEvents;
use App\Tests\Functional\FunctionalTestCase;

final class DbalProcessedEventsTest extends FunctionalTestCase
{
    private DbalProcessedEvents $processed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processed = new DbalProcessedEvents($this->em->getConnection());
    }

    public function testFirstDeliveryIsMarkedAsNew(): void
    {
        self::assertTrue($this->processed->markProcessed('consumidor-a', 'evt-1'));
    }

    public function testRepeatedDeliveryToTheSameConsumerIsADuplicate(): void
    {
        $this->processed->markProcessed('consumidor-a', 'evt-1');

        self::assertFalse($this->processed->markProcessed('consumidor-a', 'evt-1'));
    }

    public function testEachConsumerProcessesTheSameEventOnce(): void
    {
        $this->processed->markProcessed('consumidor-a', 'evt-1');

        self::assertTrue($this->processed->markProcessed('consumidor-b', 'evt-1'));
    }

    public function testDifferentEventsAreIndependent(): void
    {
        $this->processed->markProcessed('consumidor-a', 'evt-1');

        self::assertTrue($this->processed->markProcessed('consumidor-a', 'evt-2'));
    }
}
