<?php

namespace App\Tests\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationStateEnum;
use App\Message\SaleRechargeMessage;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\DTOne\DTOneCommunicationProvider;
use App\Provider\DTOne\DTOneHttpClient;
use App\Provider\DTOne\DTOneStatusMapper;
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
use Doctrine\ORM\EntityRepository;
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
 * COMR-265: un 401/403 del proveedor al crear la venta habla de NUESTRA credencial, no de la
 * venta. La transacción nunca se creó, así que la venta no se rechaza ni cambia de proveedor:
 * se libera (queda lista para reenviarse), se marca el proveedor como no disponible y se avisa.
 */
class CommunicationSaleServiceCredentialsRejectedTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private HistoricalSaleService&MockObject $historicalSaleService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private DTOneHttpClient&MockObject $dtoneClient;
    private PackageCatalogResolver&MockObject $packageCatalogResolver;
    private SaleProviderFailoverService&MockObject $failoverService;
    private MessageBusInterface&MockObject $messageBus;
    private CommunicationSaleService $service;
    private CommunicationSaleRecharge $saleRecharge;
    private CommunicationSalePackage $salePackage;

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
            ['app.phoneNumber', '53500000'],
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

        $this->dtoneClient = $this->createMock(DTOneHttpClient::class);
        $dtoneProvider = new DTOneCommunicationProvider($this->dtoneClient, new DTOneStatusMapper(), new NullLogger());
        $providerRegistry = new ProviderRegistry([$dtoneProvider]);

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
            $this->createMock(\App\Service\DispatchPauseLedger::class),
        );

        $this->saleRecharge = $this->buildPendingDtoneRecharge();
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }

    private function buildPendingDtoneRecharge(): CommunicationSaleRecharge
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
        $recharge->setProvider('DTONE');
        $recharge->setCatalogPackage($catalogPackage);
        $recharge->setDispatchExternalRef('555');
        $recharge->setDestinationAmount(5.0);
        $recharge->setDestinationCurrency('USD');
        $recharge->setTransactionId('2608100100042');
        $recharge->setPhoneNumber('53500000');
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess('SENDING');
        $this->assignId($recharge, 555);

        $saleRepo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $saleRepo->method('find')->with(555)->willReturn($recharge);

        $environmentRepo = $this->createMock(EnvironmentRepository::class);
        $environmentRepo->method('find')->with(10)->willReturn($environment);

        $this->salePackage = new CommunicationSalePackage();
        $this->salePackage->setTenant($account);
        $this->salePackage->setProvider('DTONE');
        $this->salePackage->setCatalogPackage($catalogPackage);
        $this->salePackage->setDispatchExternalRef('555');
        $this->salePackage->setTransactionId('2608100100043');
        $this->salePackage->setIdentificationNumber('A1234567');
        $this->salePackage->setName('Jane Doe');
        $this->salePackage->setCommercialOffice($this->createMock(CommunicationOffice::class));
        $this->salePackage->setNationality($this->createMock(CommunicationNationality::class));
        $this->salePackage->setState(CommunicationStateEnum::PENDING);
        $this->salePackage->setStateProcess('SENDING');
        $this->assignId($this->salePackage, 556);

        $packageRepo = $this->createMock(EntityRepository::class);
        $packageRepo->method('findOneBy')->willReturn($this->salePackage);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationSaleRecharge::class, $saleRepo],
            [CommunicationSalePackage::class, $packageRepo],
            [Environment::class, $environmentRepo],
        ]);

        return $recharge;
    }

    private function stubCredentialsRejected(int $httpStatus): void
    {
        $this->dtoneClient->method('createTransaction')
            ->willThrowException(new MyCurrentException('DTONE_CLIENT_ERROR', 'Unauthorized', $httpStatus));

        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);
    }

    /**
     * @dataProvider rejectedStatusProvider
     */
    public function testRejectedCredentialsReleaseTheSaleInsteadOfRejectingOrFailingOver(int $httpStatus): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCredentialsRejected($httpStatus);

        $this->failoverService->expects($this->never())->method('promoteToFallback');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
        $this->assertSame(CommunicationStateEnum::CREATED->value, $this->saleRecharge->getStateProcess());
        $this->assertSame('DTONE', $this->saleRecharge->getProvider());
        $this->assertNull($this->saleRecharge->getNextCheckAt());
    }

    /**
     * @dataProvider rejectedStatusProvider
     */
    public function testRejectedCredentialsMarkTheProviderUnavailableForThatEnvironment(int $httpStatus): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCredentialsRejected($httpStatus);

        $this->availabilityService->expects($this->once())
            ->method('markCredentialsRejected')
            ->with(CommunicationProviderEnum::DTONE, 'TEST', $this->stringContains('Unauthorized'));

        $this->service->invokeRechargeCommunication(555);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function rejectedStatusProvider(): iterable
    {
        yield '401' => [401];
        yield '403' => [403];
    }

    /**
     * Mismo criterio para la venta de paquetes: un 401/403 no la rechaza ni la deja esperando una
     * consulta de estado que nunca va a llegar (la transacción no se creó).
     *
     * @dataProvider rejectedStatusProvider
     */
    public function testPackageSaleWithRejectedCredentialsIsReleasedAndTheProviderMarkedUnavailable(int $httpStatus): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCredentialsRejected($httpStatus);

        $this->failoverService->expects($this->never())->method('promoteToFallback');
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->availabilityService->expects($this->once())
            ->method('markCredentialsRejected')
            ->with(CommunicationProviderEnum::DTONE, 'TEST', $this->stringContains('Unauthorized'));

        $this->service->executeNewSaleInfo(556);

        $this->assertSame(CommunicationStateEnum::PENDING, $this->salePackage->getState());
        $this->assertSame(CommunicationStateEnum::CREATED->value, $this->salePackage->getStateProcess());
        $this->assertNull($this->salePackage->getNextCheckAt());
    }

    public function testAValidationErrorStillRejectsTheSaleAndTriesTheFallback(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->dtoneClient->method('createTransaction')
            ->willThrowException(new MyCurrentException('DTONE_CLIENT_ERROR', 'Invalid credit party', 400));
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);

        $this->availabilityService->expects($this->never())->method('markCredentialsRejected');
        $this->failoverService->expects($this->once())->method('promoteToFallback')->willReturn(false);

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
    }
}
