<?php

namespace App\Tests\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderStatusQuery;
use App\Provider\Contract\ProviderStatusResult;
use App\Provider\Contract\RechargeProviderInterface;
use App\Provider\Contract\RechargeRequest;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use App\Provider\Csq\CsqStatusMapper;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Provider\TransactionStatus;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationSaleRechargeRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\BalanceService;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\CommunicationSaleService;
use App\Service\ConfigureSequenceService;
use App\Service\HistoricalSaleService;
use App\Service\NotificationCenterService;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Pricing\PriceSourceEnum;
use App\Service\Pricing\ResolvedSalePrice;
use App\Service\Provider\ProviderAvailabilityService;
use Doctrine\Common\Collections\ArrayCollection;
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
 * @covers \App\Service\CommunicationSaleService
 *
 * Verifica que CommunicationSaleService escribe el sobre homologado
 * (App\Provider\TransactionStatus) en vez de la respuesta cruda del
 * proveedor o de los literales heterogéneos que armaba a mano antes de
 * homologar. Ver /home/alex/.claude/plans/puedes-revisar-la-integracion-twinkling-comet.md.
 */
class CommunicationSaleServiceTransactionStatusTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private HistoricalSaleService&MockObject $historicalSaleService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private CsqHttpClient&MockObject $csqClient;
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
        $parameters->method('get')->willReturnMap([['app.csqPhoneNumber', '53500000']]);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        // Envelope es `final` — sin este stub, PHPUnit intenta generar un
        // valor de retorno automático para dispatch() y falla al doblarla.
        $messageBus->method('dispatch')->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));
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
        $providerRegistry = new ProviderRegistry([$csqProvider, $this->fakeEtecsaProviderThatThrows404()]);

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
            new CatalogVersionResolver($sysConfigRepo),
            $this->createMock(PackageCatalogResolver::class),
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
        );

        $this->saleRecharge = $this->buildPendingCsqRecharge();
    }

    /**
     * Fake mínimo de ETECSA que SIEMPRE lanza una excepción 404 cruda desde
     * fetchRechargeStatus() — a diferencia de CSQ/DTOne, cuyos adaptadores
     * envuelven todo en MyCurrentException y jamás dejan escapar un código
     * HTTP crudo (confirmado leyendo CsqCommunicationProvider::fetchRechargeStatus()).
     * Necesario para ejercitar la rama 404 de checkStatusOrder() sin
     * reconstruir el cliente HTTP real de ETECSA.
     */
    private function fakeEtecsaProviderThatThrows404(): RechargeProviderInterface&CommunicationProviderInterface
    {
        return new class implements RechargeProviderInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::ETECSA;
            }

            /** @return list<ProviderCapabilityEnum> */
            public function getCapabilities(): array
            {
                return [ProviderCapabilityEnum::RECHARGE];
            }

            /** @return list<ProviderConfigField> */
            public function getConfigSchema(): array
            {
                return [];
            }

            public function recharge(ProviderContext $context, RechargeRequest $request): ProviderDispatchResult
            {
                throw new \RuntimeException('not used in this test');
            }

            public function fetchRechargeStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
            {
                throw new \Exception('Not Found', 404);
            }
        };
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function buildPendingCsqRecharge(string $provider = 'CSQ'): CommunicationSaleRecharge
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
        $product->method('getProvider')->willReturn($provider);
        $product->method('getPackageType')->willReturn('Bundles');
        $product->method('getExternalRef')->willReturn('7951-2200');
        $product->method('getPackageId')->willReturn(0);

        $package = $this->createMock(CommunicationClientPackage::class);
        $package->method('resolveProduct')->willReturn($product);
        $package->method('getAmount')->willReturn(100.0);
        $package->method('getCurrency')->willReturn('USD');
        $package->method('getDestination')->willReturn(['amount' => 2200, 'unit' => 'CUP']);
        $package->method('getPromotionItems')->willReturn(new ArrayCollection());

        $packageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $packageRepo->method('getPackageById')->willReturn($package);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);
        $recharge->setTenant($account);
        $recharge->setProvider($provider);
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
            // checkStatusOrder() busca por la clase base, no por la
            // subclase — mismo repo mock, mismo objeto.
            [\App\Entity\CommunicationSaleInfo::class, $saleRepo],
            [Environment::class, $environmentRepo],
        ]);

        return $recharge;
    }

    // ---- dispatch exitoso: sobre v2, source=provider ----

    public function testCsqCompletedDispatchWritesAV2EnvelopeWithTheRawResponse(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->connection->method('executeStatement')->willReturn(1);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));

        $rawResponse = [
            'rc' => 0,
            'items' => [[
                'resultcode' => '10',
                'resultmessage' => 'Operación efectuada correctamente',
                'supplierreference' => '1786346034143',
            ]],
        ];
        $this->csqClient->method('purchase')->willReturn($rawResponse);

        $this->service->invokeRechargeCommunication(555);

        $status = $this->saleRecharge->getTransactionStatus();
        $this->assertTrue(TransactionStatus::isV2($status));
        $this->assertSame(2, $status['schemaVersion']);
        $this->assertSame('provider', $status['source']);
        $this->assertSame('COMPLETED', $status['outcome']);
        $this->assertSame('CSQ', $status['provider']);
        $this->assertSame($rawResponse, $status['raw']);
    }

    // ---- fallo interno (sin round-trip a proveedor): source=internal, raw=[] ----

    public function testInsufficientBalanceWritesAnInternalSourcedEnvelopeWithEmptyRaw(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1.0));
        $this->connection->method('executeStatement')->willReturn(1);

        $this->service->invokeRechargeCommunication(555);

        $status = $this->saleRecharge->getTransactionStatus();
        $this->assertSame('internal', $status['source']);
        $this->assertSame('REJECTED', $status['outcome']);
        $this->assertSame('INTERNAL_INSUFFICIENT_BALANCE', $status['providerCode']);
        $this->assertSame([], $status['raw']);
        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
    }

    // ---- regresión: no perder el raw del proveedor si algo falla DESPUÉS del dispatch ----

    public function testGenericExceptionAfterDispatchPreservesTheProviderRawInsteadOfWipingIt(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);

        $rawResponse = ['rc' => -1, 'items' => [['resultcode' => '927', 'resultmessage' => 'Importe incorrecto']]];
        $this->csqClient->method('purchase')->willReturn($rawResponse);

        // El dispatch resultó REJECTED (raw ya escrito); createHistoricalCommunication
        // revienta DESPUÉS de eso — antes de este cambio, el catch genérico
        // sobrescribía el transactionStatus con un literal sin el raw real.
        // Solo la PRIMERA llamada (la de la rama REJECTED) falla; la que
        // hace el propio catch al registrar el error debe poder completar.
        $historicalCallCount = 0;
        $this->historicalSaleService->method('createHistoricalCommunication')
            ->willReturnCallback(function () use (&$historicalCallCount) {
                $historicalCallCount++;
                if ($historicalCallCount === 1) {
                    throw new \RuntimeException('DB down');
                }
            });

        $this->service->invokeRechargeCommunication(555);

        $status = $this->saleRecharge->getTransactionStatus();
        $this->assertSame('internal', $status['source']);
        $this->assertSame('UNKNOWN', $status['outcome']);
        $this->assertSame($rawResponse, $status['raw'], 'El raw del proveedor no debe perderse tras la excepción posterior.');
    }

    // ---- regresión CRÍTICA: contador de reintentos en formato legacy ----

    public function testRetryCounterInLegacyRootFormatIsHonoredNotResetToZero(): void
    {
        // Reutiliza la venta ya cableada por setUp() (mismos mocks de
        // repositorio) — solo cambia lo necesario: proveedor ETECSA (para
        // que resuelva al fake que lanza 404), transactionStatus con 2
        // reintentos registrados con la clave EN LA RAÍZ (formato escrito
        // por el código ANTES de homologar). Si el lector nuevo solo
        // mirara 'retry.count', el contador se reiniciaría a 0 y la venta
        // reenviaría el envío original cada 4h indefinidamente.
        $this->saleRecharge->setProvider('ETECSA');
        $this->saleRecharge->setStateProcess('PENDING');
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $this->saleRecharge->setTransactionStatus([
            'orderId' => 1,
            'retryCount' => 2,
            'lastRetryAt' => (new \DateTimeImmutable('-5 hours'))->format(\DateTimeInterface::ATOM),
        ]);

        $this->service->checkStatusOrder(555);

        $status = $this->saleRecharge->getTransactionStatus();
        $this->assertSame(3, TransactionStatus::retryCountOf($status), 'El contador debía seguir en 2 y avanzar a 3, no reiniciarse a 0->1.');
        $this->assertSame('RETRYABLE', $status['outcome']);
        $this->assertSame(CommunicationStateEnum::CREATED->value, $this->saleRecharge->getStateProcess());
    }
}
