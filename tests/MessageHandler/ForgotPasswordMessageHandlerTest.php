<?php

namespace App\Tests\MessageHandler;

use App\Message\ForgotPasswordMessage;
use App\MessageHandler\ForgotPasswordMessageHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @covers \App\MessageHandler\ForgotPasswordMessageHandler
 */
class ForgotPasswordMessageHandlerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private ParameterBagInterface&MockObject $parameterBag;
    private ForgotPasswordMessageHandler $handler;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        $this->handler = new ForgotPasswordMessageHandler($this->mailer, $this->parameterBag);
    }

    public function testInvokeSendsEmailWithComremitOrigin(): void
    {
        $this->parameterBag->method('get')
            ->with('app.email.from')
            ->willReturn('noreply@comremit.com');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertSame('Reset password / Cambio de contraseña', $email->getSubject());
                $this->assertSame(Email::PRIORITY_HIGH, $email->getPriority());

                $to = $email->getTo();
                $this->assertCount(1, $to);
                $this->assertSame('user@example.com', $to[0]->getAddress());
                $this->assertSame('John Doe', $to[0]->getName());

                $from = $email->getFrom();
                $this->assertCount(1, $from);
                $this->assertSame('noreply@comremit.com', $from[0]->getAddress());
                $this->assertSame('No Reply (Comremit Solutions SL)', $from[0]->getName());

                return true;
            }));

        $message = new ForgotPasswordMessage('user@example.com', 'ABC123', 'comremit', 'John Doe');
        ($this->handler)($message);
    }

    public function testInvokeSendsEmailWithSendmundoOrigin(): void
    {
        $this->parameterBag->method('get')
            ->with('app.email.from')
            ->willReturn('noreply@sendmundo.com');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (Email $email) {
                $from = $email->getFrom();
                $this->assertSame('No Reply - (SendMundo SL)', $from[0]->getName());
                return true;
            }));

        $message = new ForgotPasswordMessage('jane@example.com', 'XYZ789', 'sendmundo', 'Jane Doe');
        ($this->handler)($message);
    }

    public function testInvokeDefaultsToComremitWhenOriginIsNull(): void
    {
        $this->parameterBag->method('get')
            ->with('app.email.from')
            ->willReturn('noreply@test.com');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (Email $email) {
                $from = $email->getFrom();
                $this->assertSame('No Reply (Comremit Solutions SL)', $from[0]->getName());
                return true;
            }));

        $message = new ForgotPasswordMessage('user@test.com', 'CODE01', null, 'Test User');
        ($this->handler)($message);
    }

    public function testInvokeSendsToCorrectRecipient(): void
    {
        $this->parameterBag->method('get')->willReturn('noreply@test.com');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (Email $email) {
                $to = $email->getTo();
                $this->assertSame('recipient@domain.com', $to[0]->getAddress());
                $this->assertSame('Recipient Name', $to[0]->getName());
                return true;
            }));

        $message = new ForgotPasswordMessage('recipient@domain.com', 'RESET99', 'comremit', 'Recipient Name');
        ($this->handler)($message);
    }

    public function testInvokeWithNullName(): void
    {
        $this->parameterBag->method('get')->willReturn('noreply@test.com');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (Email $email) {
                $to = $email->getTo();
                $this->assertSame('user@test.com', $to[0]->getAddress());
                return true;
            }));

        $message = new ForgotPasswordMessage('user@test.com', 'CODE', 'comremit', null);
        ($this->handler)($message);
    }
}
