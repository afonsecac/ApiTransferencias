<?php

namespace App\Tests\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationStateEnum;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use App\Provider\Csq\CsqStatusMapper;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderDispatchResolver;
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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationSaleService::invokeRechargeCommunication
 *
 * El despacho asíncrono de una venta (catalogPackage/dispatchProduct/
 * dispatchExternalRef ya persistidos en admitV2()) usa
 * PackageCatalogResolver::offerFor() para el recheck de saldo y el snapshot
 * ya persistido para el producto/destino — nunca vuelve a resolver nada.
 * Mismo escenario real de CommunicationSaleServiceCsqCompletedDispatchTest
 * (CSQ síncrono, payload real capturado en vivo).
 */
class CommunicationSaleServiceV2AsyncDispatchTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private HistoricalSaleService&MockObject $historicalSaleService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private PackageCatalogResolver&MockObject $packageCatalogResolver;
    private CsqHttpClient&MockObject $csqClient;
    private CommunicationSaleService $service;
    private CommunicationSaleRecharge $saleRecharge;
    private CommunicationPackage $catalogPackage;

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
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $this->historicalSaleService = $this->createMock(HistoricalSaleService::class);
        $this->balanceService = $this->createMock(BalanceService::class);
        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $this->availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $configureSequence = $this->createMock(ConfigureSequenceService::class);

        $this->packageCatalogResolver = $this->createMock(PackageCatalogResolver::class);
        $dispatchResolver = $this->createMock(ProviderDispatchResolver::class);

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
            $messageBus,
            $this->historicalSaleService,
            $this->balanceService,
            $notificationCenter,
            $this->availabilityService,
            $this->packageCatalogResolver,
            $dispatchResolver,
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
            $this->createMock(\App\Service\Provider\SaleProviderFailoverService::class),
        );

        $this->saleRecharge = $this->buildPendingV2CsqRecharge();
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function buildPendingV2CsqRecharge(): CommunicationSaleRecharge
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

        $this->catalogPackage = (new CommunicationPackage())
            ->setName('Cubacel V2')
            ->setDescription('Cubacel V2')
            ->setDestinationAmount(2200.0)
            ->setDestinationCurrency('CUP');
        $this->assignId($this->catalogPackage, 7);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(7);
        $recharge->setTenant($account);
        $recharge->setProvider('CSQ');
        $recharge->setTransactionId('2608100100043');
        $recharge->setPhoneNumber('53500000');
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess('SENDING');
        $recharge->setAmount(100.0);
        $recharge->setCurrency('USD');
        $recharge->setCatalogPackage($this->catalogPackage);
        $recharge->setDispatchExternalRef('7854-2200');
        $recharge->setDestinationAmount(2200.0);
        $recharge->setDestinationCurrency('CUP');
        $this->assignId($recharge, 556);

        $saleRepo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $saleRepo->method('find')->with(556)->willReturn($recharge);

        $environmentRepo = $this->createMock(EnvironmentRepository::class);
        $environmentRepo->method('find')->with(10)->willReturn($environment);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationSaleRecharge::class, $saleRepo],
            [Environment::class, $environmentRepo],
        ]);

        return $recharge;
    }

    public function testCompletesTheV2SaleUsingTheDispatchSnapshotDirectly(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        $this->packageCatalogResolver->expects($this->once())
            ->method('offerFor')
            ->with($this->catalogPackage, $this->saleRecharge->getTenant())
            ->willReturn(new ResolvedPackageOffer($this->catalogPackage, 100.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX));

        // Mismo payload real de CommunicationSaleServiceCsqCompletedDispatchTest.
        $this->csqClient->expects($this->once())
            ->method('purchase')
            ->with(
                $this->anything(),
                7854,
                $this->anything(),
                $this->anything(),
                220000,
            )
            ->willReturn([
                'rc' => 0,
                'items' => [[
                    'finalstatus' => 10,
                    'resultcode' => '10',
                    'resultmessage' => 'Operación efectuada correctamente',
                    'supplierreference' => '1786346034144',
                    'suppliertoken' => '',
                ]],
            ]);

        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);
        $this->connection->method('executeStatement')->willReturn(1);

        $this->balanceService->expects($this->once())
            ->method('createSaleBalance')
            ->with($this->saleRecharge->getTenant(), $this->saleRecharge);

        $this->service->invokeRechargeCommunication(556);

        // getState() no se puede verificar aquí: claimForCompleting() lo
        // actualiza vía UPDATE SQL crudo + em->refresh(), y $this->em está
        // enteramente mockeado (refresh() no reconsulta nada real) — mismo
        // límite ya documentado en CommunicationSaleServiceCsqCompletedDispatchTest,
        // que tampoco lo verifica.
        $this->assertSame('1786346034144', $this->saleRecharge->getTransactionOrder());
    }

    public function testRejectsWhenTheRecheckedOfferIsUnavailable(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        // Recatalogación entre admisión y despacho: ya ningún proveedor
        // cubre la tupla — offerFor() ahora devuelve UNAVAILABLE.
        $this->packageCatalogResolver->method('offerFor')
            ->willReturn(new ResolvedPackageOffer($this->catalogPackage, 0.0, 'USD', PackageOfferSourceEnum::UNAVAILABLE));

        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);
        $this->connection->method('executeStatement')->willReturn(1);

        $this->csqClient->expects($this->never())->method('purchase');
        $this->balanceService->expects($this->never())->method('createSaleBalance');

        $this->service->invokeRechargeCommunication(556);

        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
    }
}
