<?php

namespace App\Tests\Service;

use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\TransactionStatus;
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
 * COMR-266: CSQ y ETECSA rechazan el reenvío de una referencia ya usada (CSQ: «Ticket duplicado»;
 * ETECSA: un mensaje genérico) aunque la venta ORIGINAL exista y esté completada u ordenada. Aplicar
 * ese rechazo a la venta la rechazaría y liberaría su saldo aunque el proveedor la tenga procesada.
 * Ante un REJECTED de un reenvío no se aplica el rechazo ni se prueba otro proveedor: la venta
 * vuelve a PENDING y se consulta primero el estado de la original.
 */
class CommunicationSaleServiceResendRejectionTest extends TestCase
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
            $this->createMock(\App\Service\DispatchPauseLedger::class),
        );

        $this->saleRecharge = $this->buildPendingCsqRecharge();
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
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

    private function stubCsqDuplicateTicketRejection(): void
    {
        $this->csqClient->method('purchase')->willReturn([
            'rc' => -1,
            'items' => [[
                'finalstatus' => -1,
                'resultcode' => '-1',
                'resultmessage' => 'Ticket duplicado para el mismo día/máquina',
            ]],
        ]);

        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);
    }

    private function markAsAlreadySentTo(string $provider, ProviderOutcomeEnum $outcome): array
    {
        $previous = TransactionStatus::fromDispatch(
            new ProviderDispatchResult(outcome: $outcome, providerReference: 'ORD-1'),
            $provider,
        );
        $this->saleRecharge->setTransactionStatus($previous);

        return $previous;
    }

    /**
     * @dataProvider previousOutcomesThatMayHaveReachedTheProviderProvider
     */
    public function testRejectionOfAResendDoesNotRejectTheSaleNorFailOver(ProviderOutcomeEnum $previousOutcome): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCsqDuplicateTicketRejection();
        $previous = $this->markAsAlreadySentTo('CSQ', $previousOutcome);

        $this->failoverService->expects($this->never())->method('promoteToFallback');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::PENDING, $this->saleRecharge->getState());
        $this->assertSame(CommunicationStateEnum::PENDING->value, $this->saleRecharge->getStateProcess());
        $this->assertSame('CSQ', $this->saleRecharge->getProvider());
        // La venta conserva el estado de la ORIGINAL, no el del reenvío rechazado.
        $this->assertSame($previousOutcome, TransactionStatus::outcomeOf($this->saleRecharge->getTransactionStatus()));
        $this->assertSame($previous['providerReference'], $this->saleRecharge->getTransactionStatus()['providerReference']);
        // Y queda programada la consulta del estado de la original.
        $this->assertNotNull($this->saleRecharge->getNextCheckAt());
    }

    /**
     * @return iterable<string, array{ProviderOutcomeEnum}>
     */
    public static function previousOutcomesThatMayHaveReachedTheProviderProvider(): iterable
    {
        yield 'aceptada' => [ProviderOutcomeEnum::ACCEPTED];
        yield 'desconocida' => [ProviderOutcomeEnum::UNKNOWN];
        yield 'completada' => [ProviderOutcomeEnum::COMPLETED];
    }

    public function testTheIgnoredRejectionIsStillRecordedInTheHistory(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCsqDuplicateTicketRejection();
        $this->markAsAlreadySentTo('CSQ', ProviderOutcomeEnum::ACCEPTED);

        $this->historicalSaleService->expects($this->once())
            ->method('createHistoricalCommunication')
            ->with(
                555,
                CommunicationStateEnum::PENDING,
                $this->callback(static fn (array $info) => ($info['outcome'] ?? null) === ProviderOutcomeEnum::REJECTED->value),
            );

        $this->service->invokeRechargeCommunication(555);
    }

    public function testARejectionOfTheFirstSendStillRejectsTheSale(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCsqDuplicateTicketRejection();
        // Sin envío previo: transactionStatus vacío.

        $this->failoverService->expects($this->once())->method('promoteToFallback')->willReturn(false);

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
    }

    public function testARejectionAfterAPreviousRejectionStillRejectsTheSale(): void
    {
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->stubCsqDuplicateTicketRejection();
        // El envío anterior ya fue rechazado: el proveedor nunca tuvo la venta, no hay original que consultar.
        $this->markAsAlreadySentTo('CSQ', ProviderOutcomeEnum::REJECTED);

        $this->failoverService->expects($this->once())->method('promoteToFallback')->willReturn(false);

        $this->service->invokeRechargeCommunication(555);

        $this->assertSame(CommunicationStateEnum::REJECTED, $this->saleRecharge->getState());
    }
}
