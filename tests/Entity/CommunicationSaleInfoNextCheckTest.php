<?php

namespace App\Tests\Entity;

use App\Entity\CommunicationSaleRecharge;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\CommunicationSaleInfo
 */
class CommunicationSaleInfoNextCheckTest extends TestCase
{
    public function testANewSaleHasNeverBeenCheckedAndHasNoScheduledCheck(): void
    {
        $sale = new CommunicationSaleRecharge();

        $this->assertSame(0, $sale->getCheckAttempts());
        $this->assertNull($sale->getNextCheckAt());
    }

    public function testSchedulingTheFirstCheckUsesTheShortestDelayWithoutCountingAnAttempt(): void
    {
        $sale = new CommunicationSaleRecharge();
        $now = new \DateTimeImmutable('2026-10-12 12:00:00');

        $sale->scheduleNextCheck($now);

        $this->assertSame(0, $sale->getCheckAttempts());
        $this->assertEquals(new \DateTimeImmutable('2026-10-12 12:00:05'), $sale->getNextCheckAt());
    }

    public function testRecordingAnAttemptCountsItAndLengthensTheWait(): void
    {
        $sale = new CommunicationSaleRecharge();
        $now = new \DateTimeImmutable('2026-10-12 12:00:00');

        $sale->recordCheckAttempt($now);
        $this->assertSame(1, $sale->getCheckAttempts());
        $this->assertEquals(new \DateTimeImmutable('2026-10-12 12:00:15'), $sale->getNextCheckAt());

        $sale->recordCheckAttempt($now);
        $this->assertSame(2, $sale->getCheckAttempts());
        $this->assertEquals(new \DateTimeImmutable('2026-10-12 12:01:00'), $sale->getNextCheckAt());
    }

    public function testAnUncertainSaleStaysPendingButIsFlaggedAndStillQueryable(): void
    {
        $sale = new CommunicationSaleRecharge();
        $sale->setState(\App\Enums\CommunicationStateEnum::PENDING);
        $sale->setStateProcess('SENDING');
        $this->assertFalse($sale->wasSentToProvider());

        $sale->markUncertain();

        $this->assertTrue($sale->isUncertain());
        $this->assertSame(\App\Enums\CommunicationStateEnum::PENDING, $sale->getState());
        $this->assertSame(CommunicationSaleRecharge::STATE_PROCESS_UNCERTAIN, $sale->getStateProcess());
        $this->assertTrue($sale->wasSentToProvider(), 'Una venta incierta se consulta por su transactionId.');
    }
}
