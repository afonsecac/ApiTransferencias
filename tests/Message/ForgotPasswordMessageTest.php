<?php

namespace App\Tests\Message;

use App\Message\ForgotPasswordMessage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Message\ForgotPasswordMessage
 */
class ForgotPasswordMessageTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $message = new ForgotPasswordMessage('user@example.com', 'ABC123', 'comremit', 'John Doe');

        $this->assertSame('user@example.com', $message->getEmail());
        $this->assertSame('ABC123', $message->getCode());
        $this->assertSame('comremit', $message->getOrigin());
        $this->assertSame('John Doe', $message->getName());
    }

    public function testNullOrigin(): void
    {
        $message = new ForgotPasswordMessage('user@example.com', 'XYZ789', null, 'Jane Doe');

        $this->assertNull($message->getOrigin());
    }

    public function testNullName(): void
    {
        $message = new ForgotPasswordMessage('user@example.com', 'CODE01', 'sendmundo', null);

        $this->assertNull($message->getName());
    }

    public function testBothOptionalFieldsNull(): void
    {
        $message = new ForgotPasswordMessage('test@test.com', '999', null, null);

        $this->assertNull($message->getOrigin());
        $this->assertNull($message->getName());
        $this->assertSame('test@test.com', $message->getEmail());
        $this->assertSame('999', $message->getCode());
    }
}
