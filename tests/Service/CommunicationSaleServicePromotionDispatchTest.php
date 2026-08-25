<?php

namespace App\Tests\Service;

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
use App\DTO\AccountBalanceDto;
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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationSaleService::invokeRechargeCommunication
 *
 * Cubre que el despacho de una recarga de promoción usa el
 * dispatchExternalRef congelado en admisión (PromotionProviderDispatchResolver,
 * ver admitV2ForReserve()) — no un producto "de origen" resuelto de nuevo en
 * el despacho.
 */
class CommunicationSaleServicePromotionDispatchTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
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
        $historicalSaleService = $this->createMock(HistoricalSaleService::class);
        $this->balanceService = $this->createMock(BalanceService::class);
        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $this->availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $configureSequence = $this->createMock(ConfigureSequenceService::class);

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
            $messageBus,
            $historicalSaleService,
            $this->balanceService,
            $notificationCenter,
            $this->availabilityService,
            $this->packageCatalogResolver,
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
        );

        $this->saleRecharge = $this->buildPendingPromotionRecharge();
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function buildPendingPromotionRecharge(): CommunicationSaleRecharge
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
            ->setName('Promo CSQ')
            ->setDescription('Promo CSQ')
            ->setDestinationAmount(2200.0)
            ->setDestinationCurrency('CUP');
        $this->assignId($this->catalogPackage, 7);

        $this->packageCatalogResolver->method('offerFor')->with($this->catalogPackage, $account)->willReturn(
            new ResolvedPackageOffer($this->catalogPackage, 100.0, 'USD', PackageOfferSourceEnum::PROMOTION),
        );

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(7);
        $recharge->setTenant($account);
        $recharge->setProvider('CSQ');
        $recharge->setPromotionId(42);
        $recharge->setCatalogPackage($this->catalogPackage);
        // Congelado en admisión (PromotionProviderDispatchResolver, ver
        // admitV2ForReserve()) — distinto del producto "de origen" de la
        // promoción a propósito, para confirmar que el despacho usa este
        // valor y no vuelve a resolver nada.
        $recharge->setDispatchExternalRef('9999-2200');
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

    public function testUsesTheFrozenDispatchExternalRef(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->csqClient->method('purchase')->willReturn([
            'rc' => 0,
            'items' => [['resultcode' => '10', 'resultmessage' => 'OK', 'supplierreference' => 'X1']],
        ]);
        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);
        $this->connection->method('executeStatement')->willReturn(1);

        $this->csqClient->expects($this->once())
            ->method('purchase')
            ->with($this->anything(), 9999, $this->anything(), $this->anything(), $this->anything());

        $this->service->invokeRechargeCommunication(555);
    }
}
