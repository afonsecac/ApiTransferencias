<?php

namespace App\Tests\Message;

use App\Message\BalanceMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Message\BalanceMessage
 */
class BalanceMessageTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $message = new BalanceMessage('CRITICAL', 150.50, 'USD', 42);

        $this->assertSame('CRITICAL', $message->getMessageType());
        $this->assertSame(150.50, $message->getCurrentBalance());
        $this->assertSame('USD', $message->getCurrency());
        $this->assertSame(42, $message->getAccountId());
    }

    public function testCriticalMessageType(): void
    {
        $message = new BalanceMessage('CRITICAL', 0.0, 'EUR', 1);

        $this->assertSame('CRITICAL', $message->getMessageType());
    }

    public function testRiskMessageType(): void
    {
        $message = new BalanceMessage('RISK', 500.0, 'USD', 99);

        $this->assertSame('RISK', $message->getMessageType());
    }

    public function testZeroBalance(): void
    {
        $message = new BalanceMessage('CRITICAL', 0.0, 'USD', 10);

        $this->assertSame(0.0, $message->getCurrentBalance());
    }

    public function testNegativeBalance(): void
    {
        $message = new BalanceMessage('CRITICAL', -25.75, 'EUR', 5);

        $this->assertSame(-25.75, $message->getCurrentBalance());
    }
}
