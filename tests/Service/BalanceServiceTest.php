<?php

namespace App\Tests\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\EmailNotification;
use App\Repository\BalanceOperationRepository;
use App\Repository\EmailNotificationRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\BalanceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @covers \App\Service\BalanceService
 */
class BalanceServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private BalanceService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);

        $parameters = $this->createMock(ParameterBagInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);

        $this->service = new BalanceService(
            $this->em,
            $this->security,
            $parameters,
            $mailer,
            $this->logger,
            $passwordHasher,
            $environmentRepository,
            $this->sysConfigRepo,
            $serializer,
            $this->messageBus,
            $httpClient,
        );
    }

    public function testBalanceReturnsAccountBalanceDtoWhenAccountIsNull(): void
    {
        $balanceRepo = $this->createMock(BalanceOperationRepository::class);
        $balanceRepo->method('getBalanceOutput')->willReturn(100.0);

        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('find')->willReturn(null);

        $this->em->method('getRepository')
            ->willReturnMap([
                [\App\Entity\BalanceOperation::class, $balanceRepo],
                [Account::class, $accountRepo],
            ]);

        $result = $this->service->balance(1);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('USD', $result->currency);
        $this->assertSame(100.0, $result->amount);
    }

    public function testBalanceDispatchesCriticalMessageWhenBalanceBelowCritical(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCriticalBalance')->willReturn(null);
        $client->method('getMinBalance')->willReturn(null);
        $client->method('getCurrency')->willReturn('USD');

        $account = $this->createMock(Account::class);
        $account->method('getCriticalBalance')->willReturn(200.0);
        $account->method('getMinBalance')->willReturn(500.0);
        $account->method('getContractCurrency')->willReturn('USD');
        $account->method('getClient')->willReturn($client);

        $balanceRepo = $this->createMock(BalanceOperationRepository::class);
        $balanceRepo->method('getBalanceOutput')->willReturn(150.0);

        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('find')->willReturn($account);

        $notification = $this->createMock(EmailNotification::class);
        $notification->method('getCriticalTry')->willReturn(0);
        $notification->expects($this->once())->method('setCriticalTry')->with(1);

        $emailNotifRepo = $this->createMock(EmailNotificationRepository::class);
        $emailNotifRepo->method('getLastNotification')->willReturn($notification);

        $this->em->method('getRepository')
            ->willReturnMap([
                [\App\Entity\BalanceOperation::class, $balanceRepo],
                [Account::class, $accountRepo],
                [EmailNotification::class, $emailNotifRepo],
            ]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->service->balance(1);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('USD', $result->currency);
    }

    public function testBalanceDispatchesRiskMessageWhenBalanceBelowMinAndNotNotified(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCriticalBalance')->willReturn(null);
        $client->method('getMinBalance')->willReturn(null);
        $client->method('getCurrency')->willReturn('EUR');

        $account = $this->createMock(Account::class);
        $account->method('getCriticalBalance')->willReturn(50.0);
        $account->method('getMinBalance')->willReturn(500.0);
        $account->method('getContractCurrency')->willReturn('EUR');
        $account->method('getClient')->willReturn($client);

        $balanceRepo = $this->createMock(BalanceOperationRepository::class);
        $balanceRepo->method('getBalanceOutput')->willReturn(300.0);

        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('find')->willReturn($account);

        $notification = $this->createMock(EmailNotification::class);
        $notification->method('getMinInfo')->willReturn(null);
        $notification->expects($this->once())->method('setMinInfo')->with(1);

        $emailNotifRepo = $this->createMock(EmailNotificationRepository::class);
        $emailNotifRepo->method('getLastNotification')->willReturn($notification);

        $this->em->method('getRepository')
            ->willReturnMap([
                [\App\Entity\BalanceOperation::class, $balanceRepo],
                [Account::class, $accountRepo],
                [EmailNotification::class, $emailNotifRepo],
            ]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->service->balance(1);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('EUR', $result->currency);
    }

    public function testBalanceDoesNotDispatchRiskWhenAlreadyNotified(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCriticalBalance')->willReturn(null);
        $client->method('getMinBalance')->willReturn(null);
        $client->method('getCurrency')->willReturn('USD');

        $account = $this->createMock(Account::class);
        $account->method('getCriticalBalance')->willReturn(50.0);
        $account->method('getMinBalance')->willReturn(500.0);
        $account->method('getContractCurrency')->willReturn('USD');
        $account->method('getClient')->willReturn($client);

        $balanceRepo = $this->createMock(BalanceOperationRepository::class);
        $balanceRepo->method('getBalanceOutput')->willReturn(300.0);

        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('find')->willReturn($account);

        $notification = $this->createMock(EmailNotification::class);
        $notification->method('getMinInfo')->willReturn(1);

        $emailNotifRepo = $this->createMock(EmailNotificationRepository::class);
        $emailNotifRepo->method('getLastNotification')->willReturn($notification);

        $this->em->method('getRepository')
            ->willReturnMap([
                [\App\Entity\BalanceOperation::class, $balanceRepo],
                [Account::class, $accountRepo],
                [EmailNotification::class, $emailNotifRepo],
            ]);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->service->balance(1);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
    }

    public function testBalanceCreatesNewNotificationWhenNoneExists(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCriticalBalance')->willReturn(null);
        $client->method('getMinBalance')->willReturn(null);
        $client->method('getCurrency')->willReturn('USD');

        $account = $this->createMock(Account::class);
        $account->method('getCriticalBalance')->willReturn(100.0);
        $account->method('getMinBalance')->willReturn(200.0);
        $account->method('getContractCurrency')->willReturn('USD');
        $account->method('getClient')->willReturn($client);

        $balanceRepo = $this->createMock(BalanceOperationRepository::class);
        $balanceRepo->method('getBalanceOutput')->willReturn(1000.0);

        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('find')->willReturn($account);

        $emailNotifRepo = $this->createMock(EmailNotificationRepository::class);
        $emailNotifRepo->method('getLastNotification')->willReturn(null);

        $this->em->method('getRepository')
            ->willReturnMap([
                [\App\Entity\BalanceOperation::class, $balanceRepo],
                [Account::class, $accountRepo],
                [EmailNotification::class, $emailNotifRepo],
            ]);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EmailNotification::class));

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $result = $this->service->balance(1);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
    }

    public function testBalanceUsesSysConfigWhenCriticalBalanceIsNull(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getCriticalBalance')->willReturn(null);
        $client->method('getMinBalance')->willReturn(null);
        $client->method('getCurrency')->willReturn('USD');

        $account = $this->createMock(Account::class);
        $account->method('getCriticalBalance')->willReturn(null);
        $account->method('getMinBalance')->willReturn(null);
        $account->method('getContractCurrency')->willReturn('USD');
        $account->method('getClient')->willReturn($client);

        $balanceRepo = $this->createMock(BalanceOperationRepository::class);
        $balanceRepo->method('getBalanceOutput')->willReturn(5000.0);

        $accountRepo = $this->createMock(EntityRepository::class);
        $accountRepo->method('find')->willReturn($account);

        $emailNotifRepo = $this->createMock(EmailNotificationRepository::class);
        $emailNotifRepo->method('getLastNotification')->willReturn(null);

        $this->em->method('getRepository')
            ->willReturnMap([
                [\App\Entity\BalanceOperation::class, $balanceRepo],
                [Account::class, $accountRepo],
                [EmailNotification::class, $emailNotifRepo],
            ]);

        $sysConfigCritical = $this->createMock(\App\Entity\SysConfig::class);
        $sysConfigCritical->method('getPropertyValue')->willReturn('100');

        $sysConfigMin = $this->createMock(\App\Entity\SysConfig::class);
        $sysConfigMin->method('getPropertyValue')->willReturn('200');

        $this->sysConfigRepo->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($sysConfigCritical, $sysConfigMin) {
                if ($criteria['propertyName'] === 'client.critical.balance.operation') {
                    return $sysConfigCritical;
                }
                if ($criteria['propertyName'] === 'client.min.balance.operation') {
                    return $sysConfigMin;
                }
                return null;
            });

        $this->em->expects($this->once())->method('persist');

        $result = $this->service->balance(1);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame(5000.0, $result->amount);
    }
}
