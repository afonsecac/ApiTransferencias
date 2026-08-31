<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationStateEnum;
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
 * Nauta WIFI Recharge (DTOne) exige SOLO accountIdentifier (cuenta Nauta,
 * no un número de teléfono) — confirmado contra el sandbox real el
 * 2026-08-31. invokeRechargeCommunication() debe poder despachar una venta
 * así sin phoneNumber en absoluto (antes de este fix, strlen(null) sobre
 * phoneNumber habría fallado — ver el docblock de la clase para la lógica
 * de sustitución de sandbox, que solo aplica cuando SÍ hay phoneNumber).
 */
class CommunicationSaleServiceAccountIdentifierDispatchTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private DTOneHttpClient&MockObject $dtoneClient;
    private PackageCatalogResolver&MockObject $packageCatalogResolver;
    private CommunicationSaleService $service;

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
            $this->createMock(EnvironmentRepository::class),
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
            $this->createMock(\App\Service\Provider\SaleProviderFailoverService::class),
        );
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function buildPendingDtoneRecharge(?string $phoneNumber, ?string $accountIdentifier): CommunicationSaleRecharge
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
        $recharge->setDispatchExternalRef('35835');
        $recharge->setDestinationAmount(250.0);
        $recharge->setDestinationCurrency('CUP');
        $recharge->setTransactionId('2608110100042');
        $recharge->setPhoneNumber($phoneNumber);
        $recharge->setAccountIdentifier($accountIdentifier);
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

    private function acceptedDtoneResponse(): array
    {
        return [
            'id' => 999888,
            'status' => ['class' => ['id' => 1, 'message' => 'ACCEPTED'], 'id' => 10001, 'message' => 'ACCEPTED'],
        ];
    }

    public function testDispatchesWithOnlyAccountIdentifierWhenPhoneNumberIsAbsent(): void
    {
        $this->buildPendingDtoneRecharge(null, 'usuario@nauta.com.cu');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);

        $this->dtoneClient->expects($this->once())
            ->method('createTransaction')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body) => $body['credit_party_identifier'] === ['account_number' => 'usuario@nauta.com.cu']),
            )
            ->willReturn($this->acceptedDtoneResponse());

        $this->service->invokeRechargeCommunication(555);
    }

    public function testDispatchesWithBothIdentifiersWhenBothArePresentAndAppliesDtoneSandboxSwapOnlyToPhone(): void
    {
        $this->buildPendingDtoneRecharge('5356085160', 'usuario@nauta.com.cu');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);

        $this->dtoneClient->expects($this->once())
            ->method('createTransaction')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body) => $body['credit_party_identifier'] === [
                    'mobile_number' => '+5356085100',
                    'account_number' => 'usuario@nauta.com.cu',
                ]),
            )
            ->willReturn($this->acceptedDtoneResponse());

        $this->service->invokeRechargeCommunication(555);
    }
}
