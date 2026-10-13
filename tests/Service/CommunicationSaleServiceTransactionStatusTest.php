<?php

namespace App\Tests\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
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
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use App\Message\CheckSaleMessage;
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
    private PackageCatalogResolver&MockObject $packageCatalogResolver;
    private CommunicationSaleService $service;
    private CommunicationSaleRecharge $saleRecharge;
    /** Código de la excepción cruda que lanza el fake de ETECSA al consultar el estado. */
    private int $etecsaStatusErrorCode = 404;
    /** Si el fake de ETECSA acepta el envío (ACCEPTED) en vez de fallar. */
    private bool $etecsaRechargeAccepted = false;
    /** Si no es null, el fake de ETECSA responde la consulta de estado con este resultado en vez de lanzar. */
    private ?\App\Enums\ProviderOutcomeEnum $etecsaStatusOutcome = null;
    /** @var list<array{object, list<object>}> mensajes despachados al bus, con sus sellos */
    private array $dispatched = [];

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
        $messageBus->method('dispatch')->willReturnCallback(function (object $message, array $stamps = []): \Symfony\Component\Messenger\Envelope {
            $this->dispatched[] = [$message, $stamps];

            return new \Symfony\Component\Messenger\Envelope(new \stdClass());
        });
        $this->historicalSaleService = $this->createMock(HistoricalSaleService::class);
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
            $this->packageCatalogResolver,
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
            $this->createMock(\App\Service\Provider\SaleProviderFailoverService::class),
            $this->createMock(\App\Service\DispatchPauseLedger::class),
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
        $errorCode = fn (): int => $this->etecsaStatusErrorCode;
        $accepts = fn (): bool => $this->etecsaRechargeAccepted;
        $statusOutcome = fn (): ?\App\Enums\ProviderOutcomeEnum => $this->etecsaStatusOutcome;

        return new class($errorCode, $accepts, $statusOutcome) implements RechargeProviderInterface {
            public function __construct(
                private readonly \Closure $errorCode,
                private readonly \Closure $accepts,
                private readonly \Closure $statusOutcome,
            ) {
            }

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
                if (($this->accepts)()) {
                    return new ProviderDispatchResult(outcome: \App\Enums\ProviderOutcomeEnum::ACCEPTED);
                }

                throw new \RuntimeException('not used in this test');
            }

            public function fetchRechargeStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
            {
                $outcome = ($this->statusOutcome)();
                if (null !== $outcome) {
                    return new ProviderStatusResult(outcome: $outcome, message: 'respuesta simulada');
                }

                throw new \Exception('Provider error', ($this->errorCode)());
            }
        };
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
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

        $catalogPackage = $this->createMock(CommunicationPackage::class);
        $catalogPackage->method('getId')->willReturn(1);

        $this->packageCatalogResolver->method('offerFor')->with($catalogPackage, $account)->willReturn(
            new ResolvedPackageOffer($catalogPackage, 100.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX),
        );

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);
        $recharge->setTenant($account);
        $recharge->setProvider($provider);
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

    public function testRetryCounterInLegacyRootFormatIsKeptAndTheSaleIsNotResentOn404(): void
    {
        // Ventas con el contador en la RAÍZ (formato anterior a homologar): el lector debe seguir viéndolo y,
        // desde COMR-257, un 404 ya no reenvía la recarga aunque hayan pasado más de 4 h: queda incierta.
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
        $this->assertSame(2, TransactionStatus::retryCountOf($status), 'El contador heredado se conserva: no se reinicia ni avanza.');
        $this->assertSame([], $this->dispatched, 'No se reenvía.');
        $this->assertTrue($this->saleRecharge->isUncertain());
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
    }

    // ---- COMR-260 paso 2: la cadena del HTTP 400 ya no existe ----

    private function prepareEtecsaSaleReturning(int $errorCode): void
    {
        $this->saleRecharge->setProvider('ETECSA');
        $this->saleRecharge->setStateProcess('PENDING');
        $this->etecsaStatusErrorCode = $errorCode;
    }

    public function testHttp400OnTheStatusQueryDoesNotDispatchAnotherCheck(): void
    {
        $this->prepareEtecsaSaleReturning(400);

        $this->service->checkStatusOrder(555);

        // COMR-260 paso 2: la siguiente consulta la programa next_check_at (y la lanza el cron), no un
        // re-despacho de 2 s desde aquí que, repetido, formaba una cadena sin tope.
        $this->assertSame([], $this->dispatched);
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
        $this->assertSame('INTERNAL_PROVIDER_HTTP_400', $this->saleRecharge->getTransactionStatus()['providerCode']);
    }

    public function testHttp400ChainIsBoundedBecauseNothingIsRequeuedAndTheWaitGrowsUpToTheCap(): void
    {
        $this->prepareEtecsaSaleReturning(400);

        for ($i = 0; $i < 25; ++$i) {
            $this->service->checkStatusOrder(555);
        }

        $this->assertSame([], $this->dispatched, 'Veinticinco 400 seguidos no encolan ni un mensaje.');
        $this->assertSame(25, $this->saleRecharge->getCheckAttempts());
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
        $secondsUntilNextCheck = $this->saleRecharge->getNextCheckAt()->getTimestamp() - time();
        $this->assertGreaterThan(3500, $secondsUntilNextCheck, 'La espera llega al tope de 1 h.');
        $this->assertLessThanOrEqual(3600, $secondsUntilNextCheck);
    }

    // ---- COMR-260 paso 1: la consulta escribe next_check_at y check_attempts (todavía nadie los lee) ----

    public function testHttp400RecordsTheAttemptAndSchedulesTheNextCheck(): void
    {
        $this->prepareEtecsaSaleReturning(400);
        $before = new \DateTimeImmutable();

        $this->service->checkStatusOrder(555);

        $this->assertSame(1, $this->saleRecharge->getCheckAttempts());
        $this->assertNextCheckIsAbout(15, $before);
    }

    public function testOtherErrorsOnTheStatusQueryAlsoRecordTheAttempt(): void
    {
        $this->prepareEtecsaSaleReturning(500);
        $before = new \DateTimeImmutable();

        $this->service->checkStatusOrder(555);

        $this->assertSame(1, $this->saleRecharge->getCheckAttempts());
        $this->assertNextCheckIsAbout(15, $before);
    }

    public function testEachFurtherAttemptLengthensTheWait(): void
    {
        $this->prepareEtecsaSaleReturning(500);

        for ($i = 0; $i < 3; ++$i) {
            $before = new \DateTimeImmutable();
            $this->service->checkStatusOrder(555);
        }

        $this->assertSame(3, $this->saleRecharge->getCheckAttempts());
        $this->assertNextCheckIsAbout(300, $before);
    }

    public function testHttp404BeforeFourHoursStillRecordsTheAttempt(): void
    {
        $this->prepareEtecsaSaleReturning(404);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-1 hour'));
        $before = new \DateTimeImmutable();

        $this->service->checkStatusOrder(555);

        $this->assertSame(1, $this->saleRecharge->getCheckAttempts());
        $this->assertNextCheckIsAbout(15, $before);
    }

    private function assertNextCheckIsAbout(int $seconds, \DateTimeImmutable $before): void
    {
        $next = $this->saleRecharge->getNextCheckAt();
        $this->assertNotNull($next);
        $this->assertGreaterThanOrEqual($before->getTimestamp() + $seconds, $next->getTimestamp());
        $this->assertLessThanOrEqual($before->getTimestamp() + $seconds + 5, $next->getTimestamp());
    }

    public function testOtherErrorCodesOnTheStatusQueryRecordHistoryButDispatchNothing(): void
    {
        $this->prepareEtecsaSaleReturning(500);
        $this->historicalSaleService->expects($this->once())->method('createHistoricalCommunication');

        $this->service->checkStatusOrder(555);

        $this->assertSame([], $this->dispatched);
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
    }

    public function testHttp404BeforeFourHoursDoesNotResendNorChangeTheSale(): void
    {
        $this->prepareEtecsaSaleReturning(404);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-1 hour'));

        $this->service->checkStatusOrder(555);

        $this->assertSame([], $this->dispatched);
        $this->assertSame('PENDING', $this->saleRecharge->getStateProcess());
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
    }

    // ---- COMR-257: un 404 nunca reenvía ni libera saldo; la venta queda «incierta» ----

    public function testHttp404AfterTheGracePeriodMarksTheSaleUncertainWithoutResendingNorReleasingBalance(): void
    {
        $this->prepareEtecsaSaleReturning(404);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-5 hours'));

        $this->service->checkStatusOrder(555);

        $this->assertSame([], $this->dispatched, 'Un 404 no reenvía la recarga.');
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState(), 'Sigue PENDING: cuenta como saldo comprometido.');
        $this->assertSame(\App\Entity\CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN, $this->saleRecharge->getStateProcess());
        $this->assertTrue($this->saleRecharge->isUncertain());
        $this->assertSame('INTERNAL_PROVIDER_NOT_FOUND_UNCERTAIN', $this->saleRecharge->getTransactionStatus()['providerCode']);
    }

    public function testHttp404WithTheLegacyRetryCounterAlsoGoesUncertainInsteadOfRejected(): void
    {
        $this->prepareEtecsaSaleReturning(404);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-10 hours'));
        $this->saleRecharge->setTransactionStatus(['orderId' => 1, 'retryCount' => 3]);

        $this->service->checkStatusOrder(555);

        $this->assertSame([], $this->dispatched);
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
        $this->assertTrue($this->saleRecharge->isUncertain());
    }

    public function testUncertainSaleKeepsItsScheduledCheckSoItIsQueriedAgainNeverResent(): void
    {
        $this->prepareEtecsaSaleReturning(404);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-5 hours'));

        $this->service->checkStatusOrder(555);

        $this->assertNotNull($this->saleRecharge->getNextCheckAt());
    }

    public function testHttp404OnAPackageSaleAlsoGoesUncertainInsteadOfRejected(): void
    {
        $this->prepareEtecsaSaleReturning(404);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-5 hours'));

        $this->service->checkStatusOrder(555);

        $this->assertNotSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
    }

    public function testProviderRejectionOnAnUncertainSaleDoesNotRejectNorFailOver(): void
    {
        $this->prepareEtecsaSaleReturning(404);
        $this->saleRecharge->markUncertain();
        $this->etecsaStatusOutcome = \App\Enums\ProviderOutcomeEnum::REJECTED;

        $this->service->checkStatusOrder(555);

        $this->assertSame([], $this->dispatched, 'Sin envío a un proveedor secundario.');
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState(), 'Un «no la conozco» no es un rechazo confirmado: el saldo sigue reservado.');
        $this->assertTrue($this->saleRecharge->isUncertain());
    }

    // ---- COMR-257: el reclamo SENDING marca la hora, para poder rescatar las ventas que se quedan colgadas ----

    public function testClaimingASaleForSendingStampsUpdatedAtSoItsAgeCanBeMeasured(): void
    {
        $statements = [];
        $this->connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 1;
        });
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->saleRecharge->setStateProcess(CommunicationStateEnum::CREATED->value);

        $this->service->invokeRechargeCommunication(555);

        $claims = array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'state_process = :sending'));
        $this->assertNotSame([], $claims, 'El reclamo de SENDING debe ejecutarse.');
        $this->assertStringContainsString('updated_at', array_values($claims)[0], 'Sin updated_at, una venta antigua reclamada hoy parecería colgada al instante.');
    }

    // ---- COMR-260 paso 2: una venta pendiente más de 24 h pasa a «incierta» ----

    private function pendingCsqSaleCreated(string $ago): void
    {
        $this->saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable($ago));
        // El proveedor no responde: la venta sigue pendiente tras la consulta.
        $this->csqClient->method('getTransactionInfo')->willThrowException(new \App\Exception\MyCurrentException('CSQ_DOWN', 'sin respuesta', 503));
    }

    public function testASaleStillPendingAfter24HoursBecomesUncertainButKeepsItsReservedBalance(): void
    {
        $this->pendingCsqSaleCreated('-25 hours');
        $this->historicalSaleService->expects($this->atLeastOnce())->method('createHistoricalCommunication');

        $this->service->checkStatusOrder(555);

        $this->assertTrue($this->saleRecharge->isUncertain());
        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState(), 'Sigue PENDING: el importe sigue comprometido.');
        // La consulta que sigue reescribe el sobre de estado, pero el bloque de reintento conserva el motivo.
        $this->assertStringContainsString('more than 24 hours', $this->saleRecharge->getTransactionStatus()['retry']['reason']);
        $this->assertTrue($this->saleRecharge->getTransactionStatus()['retry']['uncertain']);
        $this->assertSame([], $this->dispatched, 'No se reenvía nada.');
        $this->assertNotNull($this->saleRecharge->getNextCheckAt(), 'Se sigue consultando.');
    }

    public function testASaleYoungerThan24HoursIsNotMarkedUncertain(): void
    {
        $this->pendingCsqSaleCreated('-23 hours');

        $this->service->checkStatusOrder(555);

        $this->assertFalse($this->saleRecharge->isUncertain());
        $this->assertSame(CommunicationStateEnum::PENDING->value, $this->saleRecharge->getStateProcess());
    }

    public function testAnAlreadyUncertainSaleIsNotMarkedAgainForBeingOld(): void
    {
        $this->pendingCsqSaleCreated('-30 hours');
        $this->saleRecharge->markUncertain();

        $this->service->checkStatusOrder(555);

        $this->assertTrue($this->saleRecharge->isUncertain());
        $this->assertArrayNotHasKey('retry', $this->saleRecharge->getTransactionStatus(), 'No se vuelve a marcar (ni a escribir el motivo) cada hora.');
    }

    public function testAnOldSaleThatTheProviderConfirmsAsCompletedIsStillCompleted(): void
    {
        $this->saleRecharge->setStateProcess(CommunicationStateEnum::PENDING->value);
        $this->saleRecharge->setCreatedAt(new \DateTimeImmutable('-40 hours'));
        $this->csqClient->method('getTransactionInfo')->willReturn(['rc' => 0, 'items' => [['resultcode' => '10', 'supplierreference' => '999', 'resultmessage' => 'ok']]]);
        $this->connection->method('executeStatement')->willReturn(1);
        $this->balanceService->expects($this->once())->method('createSaleBalance');

        $this->service->checkStatusOrder(555);
    }

    // ---- COMR-261: un rechazo síncrono debe dejar state process coherente con state ----

    public function testSynchronousProviderRejectionLeavesStateProcessRejectedToo(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);
        // Rechazo determinista de CSQ (importe incorrecto) y sin proveedor secundario al que pasar.
        $this->csqClient->method('purchase')->willReturn(['rc' => -1, 'items' => [['resultcode' => '927', 'resultmessage' => 'Importe incorrecto']]]);

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
        $this->assertSame(
            CommunicationStateEnum::REJECTED->value,
            $this->saleRecharge->getStateProcess(),
            'Una venta rechazada no puede seguir con state process Pending (caso de la venta 5387 de producción).',
        );
    }
}
