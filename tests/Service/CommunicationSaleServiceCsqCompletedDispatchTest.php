<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationStateEnum;
use App\Provider\Contract\ProviderContext;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use App\Provider\Csq\CsqStatusMapper;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationClientPackageRepository;
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
 * Bug potencial (2026-08-10): CSQ es el primer proveedor cuyo dispatch
 * inicial puede devolver COMPLETED directamente (Purchase es síncrono) en
 * vez de ACCEPTED (ETECSA/DTOne siempre confirman después vía poll). Sin
 * una rama dedicada en invokeRechargeCommunication(), una recarga CSQ
 * exitosa se habría quedado en PENDING para siempre: nunca se
 * finalizaba (sin CheckSaleMessage, porque solo ACCEPTED lo programa) ni
 * se le acreditaba/debitaba el balance al cliente. Este test ejercita el
 * camino real completo — CommunicationSaleService -> CsqCommunicationProvider
 * (con CsqHttpClient mockeado devolviendo la respuesta real capturada en
 * vivo el 2026-08-10) -> CsqStatusMapper — para probar que la venta termina
 * COMPLETED, con balance y con histórico, sin usar dobles falsos que
 * pudieran ocultar una desconexión de tipos/contrato real.
 */
class CommunicationSaleServiceCsqCompletedDispatchTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private HistoricalSaleService&MockObject $historicalSaleService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private CsqHttpClient&MockObject $csqClient;
    private CommunicationSaleService $service;
    private CommunicationSaleRecharge $saleRecharge;
    private ParameterBagInterface&MockObject $parameters;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($this->connection);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getTableName')->willReturn('communication_sale_info');
        $this->em->method('getClassMetadata')->willReturn($classMetadata);

        $security = $this->createMock(Security::class);
        $this->parameters = $this->createMock(ParameterBagInterface::class);
        $this->parameters->method('get')->willReturnMap([
            ['app.csqPhoneNumber', '53500000'],
        ]);
        $parameters = $this->parameters;
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
            $this->historicalSaleService,
            $this->balanceService,
            $notificationCenter,
            $this->availabilityService,
            $salePriceResolver,
            new \App\Service\Catalog\CatalogVersionResolver($sysConfigRepo),
            $this->createMock(\App\Service\Pricing\PackageCatalogResolver::class),
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
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

        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getProvider')->willReturn('CSQ');
        $product->method('getPackageType')->willReturn('Bundles');
        $product->method('getExternalRef')->willReturn('7951-2200');
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
        // transactionId real: YYMMDD(6) + tipo(2) + secuencia(5) — el mismo
        // formato que CommunicationSaleService::processRecharge() genera.
        $recharge->setTransactionId('2608100100042');
        $recharge->setPhoneNumber('53500000');
        $recharge->setState(CommunicationStateEnum::PENDING);
        $recharge->setStateProcess('SENDING');
        $this->assignId($recharge, 555);

        $saleRepo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $saleRepo->method('find')->with(555)->willReturn($recharge);

        $environmentRepo = $this->createMock(\App\Repository\EnvironmentRepository::class);
        $environmentRepo->method('find')->with(10)->willReturn($environment);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $packageRepo],
            [CommunicationSaleRecharge::class, $saleRepo],
            [Environment::class, $environmentRepo],
        ]);

        return $recharge;
    }

    public function testCompletesTheSaleAndCreatesBalanceWhenCsqPurchaseSucceedsSynchronously(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        // Payload real capturado en vivo el 2026-08-10 (POST purchase de CSQ).
        $this->csqClient->method('purchase')->willReturn([
            'rc' => 0,
            'items' => [[
                'finalstatus' => 10,
                'resultcode' => '10',
                'resultmessage' => 'Operación efectuada correctamente',
                'supplierreference' => '1786346034143',
                'suppliertoken' => '',
            ]],
        ]);

        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);

        // claimForSending: SENDING -> ok (ya está en SENDING en el setup,
        // simula que ya pasó por ahí); claimForCompleting: PENDING -> COMPLETED.
        $this->connection->method('executeStatement')->willReturn(1);

        $this->balanceService->expects($this->once())
            ->method('createSaleBalance')
            ->with($this->saleRecharge->getTenant(), $this->saleRecharge);

        $this->historicalSaleService->expects($this->once())
            ->method('createHistoricalCommunication')
            ->with($this->saleRecharge->getId(), CommunicationStateEnum::COMPLETED, $this->anything());

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame('1786346034143', $this->saleRecharge->getTransactionOrder());
    }

    public function testDoesNotCreateBalanceWhenCsqRejectsTheAmount(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        // Payload real capturado en vivo (rechazo de negocio).
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

        $this->balanceService->expects($this->never())->method('createSaleBalance');

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
    }

    public function testSwapsThePhoneNumberForCsqDummyAccountInTestWhenItEndsInSixty(): void
    {
        // Mismo criterio ya existente para ETECSA (checkPhone === "60") —
        // confirmado en vivo el 2026-08-11: el número dummy "53500000" es
        // el único que CSQ acepta en su sandbox TEST, cualquier número real
        // rechaza con resultcode 991 sin importar que el body esté correcto.
        $this->saleRecharge->setPhoneNumber('5356085160');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        $this->csqClient->expects($this->once())
            ->method('purchase')
            ->with($this->anything(), $this->anything(), $this->anything(), '53500000', $this->anything())
            ->willReturn([
                'rc' => 0,
                'items' => [['resultcode' => '10', 'resultmessage' => 'OK', 'supplierreference' => 'X1']],
            ]);

        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);
        $this->connection->method('executeStatement')->willReturn(1);

        $this->service->invokeRechargeCommunication(555);
    }

    public function testDoesNotSwapThePhoneNumberForCsqWhenItDoesNotEndInSixty(): void
    {
        $this->saleRecharge->setPhoneNumber('5356085136');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        $this->csqClient->expects($this->once())
            ->method('purchase')
            ->with($this->anything(), $this->anything(), $this->anything(), '5356085136', $this->anything())
            ->willReturn([
                'rc' => 0,
                'items' => [['resultcode' => '10', 'resultmessage' => 'OK', 'supplierreference' => 'X1']],
            ]);

        $balance = new AccountBalanceDto('USD', 1000.0);
        $this->balanceService->method('balance')->willReturn($balance);
        $this->connection->method('executeStatement')->willReturn(1);

        $this->service->invokeRechargeCommunication(555);
    }
}
