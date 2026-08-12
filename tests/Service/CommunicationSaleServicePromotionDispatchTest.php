<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
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
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationPromotionsRepository;
use App\Repository\CommunicationSaleRechargeRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\BalanceService;
use App\Service\CommunicationSaleService;
use App\Service\ConfigureSequenceService;
use App\Service\HistoricalSaleService;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Pricing\PriceSourceEnum;
use App\Service\Pricing\ResolvedSalePrice;
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
 * Cubre la preferencia por dispatchExternalRef (congelado en admisión por
 * PromotionProviderDispatchResolver, ver admitLegacy()) sobre
 * promotion->getProduct() al resolver el productCode a despachar — el
 * punto exacto donde antes se ignoraba el proveedor real de la venta y
 * siempre se usaba el producto "de origen" de la promoción.
 */
class CommunicationSaleServicePromotionDispatchTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private CsqHttpClient&MockObject $csqClient;
    private CommunicationSaleService $service;
    private CommunicationSaleRecharge $saleRecharge;
    private CommunicationPromotions&MockObject $promotion;

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

        $salePriceResolver = $this->createMock(PackageSalePriceResolver::class);
        $salePriceResolver->method('resolve')->willReturn(
            new ResolvedSalePrice(100.0, 'USD', PriceSourceEnum::PRODUCT),
        );

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
            $salePriceResolver,
            new \App\Service\Catalog\CatalogVersionResolver($sysConfigRepo),
            $this->createMock(\App\Service\Pricing\PackageCatalogResolver::class),
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

        // Producto "de origen" de la promoción — el que se usaba SIEMPRE
        // antes de este cambio, sin importar el proveedor real de la venta.
        $defaultProduct = $this->createMock(CommunicationProduct::class);
        $defaultProduct->method('getProvider')->willReturn('CSQ');
        $defaultProduct->method('getExternalRef')->willReturn('1111-2200');

        $this->promotion = $this->createMock(CommunicationPromotions::class);
        $this->promotion->method('getProduct')->willReturn($defaultProduct);

        $promotionRepo = $this->createMock(CommunicationPromotionsRepository::class);
        $promotionRepo->method('getActivePromotionById')->with(42)->willReturn($this->promotion);

        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getProvider')->willReturn('CSQ');
        $product->method('getPackageType')->willReturn('Bundles');
        $product->method('getExternalRef')->willReturn('1111-2200');
        $product->method('getPackageId')->willReturn(0);

        $package = $this->createMock(CommunicationClientPackage::class);
        $package->method('resolveProduct')->willReturn($product);
        $package->method('getAmount')->willReturn(100.0);
        $package->method('getCurrency')->willReturn('USD');
        $package->method('getDestination')->willReturn(['amount' => 2200, 'unit' => 'CUP']);
        $package->method('getPromotionItems')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $packageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $packageRepo->method('getPackageById')->willReturn($package);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);
        $recharge->setTenant($account);
        $recharge->setProvider('CSQ');
        $recharge->setPromotionId(42);
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
            [CommunicationClientPackage::class, $packageRepo],
            [CommunicationSaleRecharge::class, $saleRepo],
            [Environment::class, $environmentRepo],
            [CommunicationPromotions::class, $promotionRepo],
        ]);

        return $recharge;
    }

    private function stubSuccessfulPurchase(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->csqClient->method('purchase')->willReturn([
            'rc' => 0,
            'items' => [['resultcode' => '10', 'resultmessage' => 'OK', 'supplierreference' => 'X1']],
        ]);
        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);
        $this->connection->method('executeStatement')->willReturn(1);
    }

    public function testUsesTheFrozenDispatchExternalRefInsteadOfThePromotionsDefaultProduct(): void
    {
        // Reserva creada DESPUÉS de este cambio: admitLegacy() ya congeló
        // el producto resuelto por PromotionProviderDispatchResolver para
        // el proveedor real de la venta — distinto del producto "de
        // origen" de la promoción (articleId 1111).
        $this->saleRecharge->setDispatchExternalRef('9999-2200');
        $this->stubSuccessfulPurchase();

        $this->csqClient->expects($this->once())
            ->method('purchase')
            ->with($this->anything(), 9999, $this->anything(), $this->anything(), $this->anything());

        $this->service->invokeRechargeCommunication(555);
    }

    public function testFallsBackToThePromotionsDefaultProductWhenNoDispatchExternalRefIsFrozen(): void
    {
        // Reserva creada ANTES de este cambio (o cualquier fila histórica):
        // dispatchExternalRef nunca se pobló — debe comportarse EXACTAMENTE
        // igual que antes (regresión crítica).
        $this->stubSuccessfulPurchase();

        $this->csqClient->expects($this->once())
            ->method('purchase')
            ->with($this->anything(), 1111, $this->anything(), $this->anything(), $this->anything());

        $this->service->invokeRechargeCommunication(555);
    }
}
