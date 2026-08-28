<?php

namespace App\Tests\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationStateEnum;
use App\Message\SaleRechargeMessage;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use App\Provider\Csq\CsqStatusMapper;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationSaleRechargeRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\BalanceService;
use App\Service\CommunicationSaleService;
use App\Service\ConfigureSequenceService;
use App\Service\HistoricalSaleService;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\Provider\SaleProviderFailoverService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationSaleService::invokeRechargeCommunication
 *
 * Cuando el proveedor rechaza una recarga (REJECTED, no UNKNOWN — ver
 * ProviderOutcomeEnum), CommunicationSaleService intenta el proveedor
 * secundario vía SaleProviderFailoverService ANTES de resolver la venta
 * como REJECTED — mismo escenario de CommunicationSaleServiceCsqCompletedDispatchTest::testDoesNotCreateBalanceWhenCsqRejectsTheAmount(),
 * pero con el failover activado.
 */
class CommunicationSaleServiceFailoverTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private HistoricalSaleService&MockObject $historicalSaleService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private CsqHttpClient&MockObject $csqClient;
    private PackageCatalogResolver&MockObject $packageCatalogResolver;
    private SaleProviderFailoverService&MockObject $failoverService;
    private MessageBusInterface&MockObject $messageBus;
    private CommunicationSaleService $service;
    private CommunicationSaleRecharge $saleRecharge;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($this->connection);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getTableName')->willReturn('communication_sale_info');
        $this->em->method('getClassMetadata')->willReturn($classMetadata);

        $security = $this->createMock(Security::class);
        $parameters = $this->createMock(ParameterBagInterface::class);
        $parameters->method('get')->willReturnMap([
            ['app.csqPhoneNumber', '53500000'],
        ]);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->historicalSaleService = $this->createMock(HistoricalSaleService::class);
        $this->balanceService = $this->createMock(BalanceService::class);
        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $this->availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $configureSequence = $this->createMock(ConfigureSequenceService::class);
        $this->failoverService = $this->createMock(SaleProviderFailoverService::class);

        $this->packageCatalogResolver = $this->createMock(PackageCatalogResolver::class);

        $routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $providerResolver = new ProviderResolver($sysConfigRepo, $routingRepo, new NullLogger());
        $providerContextFactory = new ProviderContextFactory($providerResolver);

        $this->csqClient = $this->createMock(CsqHttpClient::class);
        $csqProvider = new CsqCommunicationProvider($this->csqClient, new CsqStatusMapper(), new NullLogger());
        $providerRegistry = new ProviderRegistry([$csqProvider]);

        $this->service = new CommunicationSaleService(
            $this->em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo,
            $serializer,
            $providerRegistry,
            $providerResolver,
            $providerContextFactory,
            $configureSequence,
            $this->messageBus,
            $this->historicalSaleService,
            $this->balanceService,
            $notificationCenter,
            $this->availabilityService,
            $this->packageCatalogResolver,
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
            $this->failoverService,
        );

        $this->saleRecharge = $this->buildPendingCsqRecharge();
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function buildPendingCsqRecharge(): CommunicationSaleRecharge
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);
        $environment->method('getType')->willReturn('TEST');

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);
        $account->method('getId')->willReturn(99);

        $catalogPackage = $this->createMock(CommunicationPackage::class);
        $catalogPackage->method('getId')->willReturn(1);

        $this->packageCatalogResolver->method('offerFor')->with($catalogPackage, $account)->willReturn(
            new ResolvedPackageOffer($catalogPackage, 100.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
        );

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);
        $recharge->setTenant($account);
        $recharge->setProvider('CSQ');
        $recharge->setCatalogPackage($catalogPackage);
        $recharge->setDispatchExternalRef('7951-2200');
        $recharge->setDestinationAmount(2200.0);
        $recharge->setDestinationCurrency('CUP');
        $recharge->setTransactionId('2608100100042');
        $recharge->setPhoneNumber('53500000');
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess('SENDING');
        $this->assignId($recharge, 555);

        $saleRepo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $saleRepo->method('find')->with(555)->willReturn($recharge);

        $environmentRepo = $this->createMock(EnvironmentRepository::class);
        $environmentRepo->method('find')->with(10)->willReturn($environment);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationSaleRecharge::class, $saleRepo],
            [Environment::class, $environmentRepo],
        ]);

        return $recharge;
    }

    private function stubCsqRejection(): void
    {
        // Mismo payload de rechazo real que CommunicationSaleServiceCsqCompletedDispatchTest::testDoesNotCreateBalanceWhenCsqRejectsTheAmount().
        $this->csqClient->method('purchase')->willReturn([
            'rc' => -1,
            'items' => [[
                'finalstatus' => -1,
                'resultcode' => '927',
                'resultmessage' => 'Importe incorrecto para la ruta 993',
            ]],
        ]);

        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);
        $this->connection->method('executeStatement')->willReturn(1);
    }

    public function testPromotesToTheFallbackProviderAndReenqueuesInsteadOfRejecting(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCsqRejection();

        // El failover service muta la venta él mismo (comportamiento real
        // documentado en su propio test suite) — aquí solo se verifica que
        // CommunicationSaleService reacciona a `true` reencolando en vez de
        // marcar REJECTED.
        $this->failoverService->method('promoteToFallback')
            ->willReturnCallback(function ($sale) {
                $sale->setProvider('DTONE');

                return true;
            });

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn ($message) => $message instanceof SaleRechargeMessage))
            ->willReturn(new Envelope(new SaleRechargeMessage(555)));

        $this->service->invokeRechargeCommunication(555);

        $this->assertNotSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
        $this->assertSame('DTONE', $this->saleRecharge->getProvider());
    }

    public function testFallsBackToRejectedWhenNoFallbackCandidateIsAvailable(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCsqRejection();

        $this->failoverService->method('promoteToFallback')->willReturn(false);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
        $this->assertSame('CSQ', $this->saleRecharge->getProvider());
    }
}
