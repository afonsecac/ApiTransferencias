<?php

namespace App\Tests\Command;

use App\Command\MonitorAlertCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * @covers \App\Command\MonitorAlertCommand
 */
class MonitorAlertCommandTest extends TestCase
{
    public function testSendsThePlainTextAlertToTheRecipient(): void
    {
        $sent = [];
        $tester = $this->tester(function (RawMessage $message) use (&$sent): void { $sent[] = $message; });

        $exit = $tester->execute(['subject' => 'ALERTA API', 'body' => 'La API no responde', '--to' => 'ops@example.test']);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertCount(1, $sent);
        /** @var Email $email */
        $email = $sent[0];
        $this->assertSame('ALERTA API', $email->getSubject());
        $this->assertSame('La API no responde', $email->getTextBody());
        $this->assertSame('ops@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame('noreply@example.test', $email->getFrom()[0]->getAddress());
    }

    public function testRecipientIsRequired(): void
    {
        $tester = $this->tester(fn () => $this->fail('No debe enviar sin destinatario.'));

        $this->assertSame(Command::INVALID, $tester->execute(['subject' => 's', 'body' => 'b']));
        $this->assertStringContainsString('--to', $tester->getDisplay());
    }

    public function testRejectsAnInvalidRecipient(): void
    {
        $tester = $this->tester(fn () => $this->fail('No debe enviar a un destinatario inválido.'));

        $this->assertSame(Command::INVALID, $tester->execute(['subject' => 's', 'body' => 'b', '--to' => 'no-es-un-correo']));
    }

    public function testTransportFailureIsReportedAsFailureNotAnException(): void
    {
        // El monitor decide qué hacer si el aviso no sale (reintentar en el siguiente ciclo).
        $tester = $this->tester(function (): void { throw new TransportException('SMTP caído'); });

        $exit = $tester->execute(['subject' => 's', 'body' => 'b', '--to' => 'ops@example.test']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('SMTP caído', $tester->getDisplay());
    }

    private function tester(callable $onSend): CommandTester
    {
        $mailer = new class($onSend) implements MailerInterface {
            public function __construct(private $onSend) {}

            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
                ($this->onSend)($message);
            }
        };

        return new CommandTester(new MonitorAlertCommand($mailer, 'noreply@example.test'));
    }
}
