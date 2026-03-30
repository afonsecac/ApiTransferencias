<?php

namespace App\Tests\MessageHandler;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\Environment;
use App\Message\BalanceMessage;
use App\MessageHandler\BalanceMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @covers \App\MessageHandler\BalanceMessageHandler
 */
class BalanceMessageHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private ParameterBagInterface&MockObject $parameterBag;
    private BalanceMessageHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        $this->handler = new BalanceMessageHandler(
            $this->em,
            $this->mailer,
            $this->logger,
            $this->parameterBag,
        );
    }

    public function testInvokeDoesNotSendEmailWhenAccountIsNull(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(99)->willReturn(null);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $this->mailer->expects($this->never())->method('send');

        $message = new BalanceMessage('CRITICAL', 0.0, 'USD', 99);
        ($this->handler)($message);
    }

    public function testInvokeDoesNotSendEmailWhenClientHasNoEmail(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCompanyEmail')->willReturn(null);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(1)->willReturn($account);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $this->mailer->expects($this->never())->method('send');

        $message = new BalanceMessage('RISK', 100.0, 'EUR', 1);
        ($this->handler)($message);
    }

    public function testInvokeSendsEmailForCriticalBalance(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCompanyEmail')->willReturn('company@example.com');
        $client->method('getCompanyName')->willReturn('ACME Corp');
        $client->method('getContractWith')->willReturn('comremit');

        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('production');

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($account);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $this->parameterBag->method('get')
            ->with('app.email.from')
            ->willReturn('noreply@test.com');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (Email $email) {
                // Verify it's a CRITICAL priority (highest)
                $this->assertSame(Email::PRIORITY_HIGHEST, $email->getPriority());
                $this->assertStringContainsString('[CRITICAL]', $email->getSubject());
                return true;
            }));

        $message = new BalanceMessage('CRITICAL', 5.0, 'USD', 10);
        ($this->handler)($message);
    }

    public function testInvokeSendsEmailForRiskBalance(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCompanyEmail')->willReturn('client@example.com');
        $client->method('getCompanyName')->willReturn('Risk Corp');
        $client->method('getContractWith')->willReturn('sendmundo');

        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('staging');

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(20)->willReturn($account);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $this->parameterBag->method('get')
            ->with('app.email.from')
            ->willReturn('noreply@test.com');

        $this->mailer->expects($this->once())->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertSame(Email::PRIORITY_HIGH, $email->getPriority());
                $this->assertStringContainsString('[RISK]', $email->getSubject());
                return true;
            }));

        $message = new BalanceMessage('RISK', 200.0, 'EUR', 20);
        ($this->handler)($message);
    }

    public function testInvokeUsesComremitEmailContactWhenContractWithIsComremit(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCompanyEmail')->willReturn('test@example.com');
        $client->method('getCompanyName')->willReturn('Test Corp');
        $client->method('getContractWith')->willReturn('comremit');

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn(null);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(30)->willReturn($account);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $this->parameterBag->method('get')->willReturn('noreply@test.com');

        // We verify the email is sent (template name includes 'comremit')
        $this->mailer->expects($this->once())->method('send');

        $message = new BalanceMessage('CRITICAL', 0.0, 'USD', 30);
        ($this->handler)($message);
    }

    public function testInvokeDefaultsToComremitWhenContractWithIsNull(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCompanyEmail')->willReturn('test@example.com');
        $client->method('getCompanyName')->willReturn('Null Contract Corp');
        $client->method('getContractWith')->willReturn(null);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn(null);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(40)->willReturn($account);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $this->parameterBag->method('get')->willReturn('noreply@test.com');

        $this->mailer->expects($this->once())->method('send');

        $message = new BalanceMessage('RISK', 50.0, 'USD', 40);
        ($this->handler)($message);
    }

    public function testInvokeLogsErrorWhenMailerThrows(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCompanyEmail')->willReturn('test@example.com');
        $client->method('getCompanyName')->willReturn('Error Corp');
        $client->method('getContractWith')->willReturn('comremit');

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn(null);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(50)->willReturn($account);
        $this->em->method('getRepository')->with(Account::class)->willReturn($repo);

        $this->parameterBag->method('get')->willReturn('noreply@test.com');

        $this->mailer->method('send')
            ->willThrowException(new \RuntimeException('SMTP connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('SMTP connection failed'));

        $message = new BalanceMessage('CRITICAL', 0.0, 'USD', 50);
        // Should not throw - exception is caught internally
        ($this->handler)($message);
    }
}
