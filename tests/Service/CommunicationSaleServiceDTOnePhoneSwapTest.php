<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
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
 * Documentado por DTOne (https://developers.dtone.com/reference/sandbox):
 * su sandbox simula el resultado según los ÚLTIMOS 3 DÍGITOS del número de
 * destino ("100"/"200"/"300" = COMPLETED sin PIN), no un número dummy fijo
 * como ETECSA/CSQ — por eso aquí se reemplaza solo el sufijo, conservando
 * el resto del número real del cliente.
 */
class CommunicationSaleServiceDTOnePhoneSwapTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;
    private BalanceService&MockObject $balanceService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private DTOneHttpClient&MockObject $dtoneClient;
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

        $salePriceResolver = $this->createMock(PackageSalePriceResolver::class);
        $salePriceResolver->method('resolve')->willReturn(
            new ResolvedSalePrice(100.0, 'USD', PriceSourceEnum::PRODUCT),
        );

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
            $salePriceResolver,
            new \App\Service\Catalog\CatalogVersionResolver($sysConfigRepo),
            $this->createMock(\App\Service\Pricing\PackageCatalogResolver::class),
            $this->createMock(\App\Provider\ProviderDispatchResolver::class),
            $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class),
        );

    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function buildPendingDtoneRecharge(string $phoneNumber): CommunicationSaleRecharge
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
        $product->method('getProvider')->willReturn('DTONE');
        $product->method('getPackageType')->willReturn('FIXED_VALUE_RECHARGE');
        $product->method('getExternalRef')->willReturn('35718');
        $product->method('getPackageId')->willReturn(35718);

        $package = $this->createMock(CommunicationClientPackage::class);
        $package->method('resolveProduct')->willReturn($product);
        $package->method('getAmount')->willReturn(100.0);
        $package->method('getCurrency')->willReturn('USD');
        $package->method('getDestination')->willReturn(['amount' => 250, 'unit' => 'CUP']);
        $package->method('getPromotionItems')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $packageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $packageRepo->method('getPackageById')->willReturn($package);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);
        $recharge->setTenant($account);
        $recharge->setProvider('DTONE');
        $recharge->setTransactionId('2608110100042');
        $recharge->setPhoneNumber($phoneNumber);
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

    public function testReplacesOnlyTheLastThreeDigitsWithDTOneCompletedSuffixWhenPhoneEndsInSixty(): void
    {
        $this->saleRecharge = $this->buildPendingDtoneRecharge('5356085160');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);

        $this->dtoneClient->expects($this->once())
            ->method('createTransaction')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body) => ($body['credit_party_identifier']['mobile_number'] ?? null) === '+5356085100'),
            )
            ->willReturn($this->acceptedDtoneResponse());

        $this->service->invokeRechargeCommunication(555);
    }

    public function testKeepsTheRealPhoneNumberWhenItDoesNotEndInSixty(): void
    {
        $this->saleRecharge = $this->buildPendingDtoneRecharge('5356085136');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->balanceService->method('balance')->willReturn(new AccountBalanceDto('USD', 1000.0));
        $this->connection->method('executeStatement')->willReturn(1);

        $this->dtoneClient->expects($this->once())
            ->method('createTransaction')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body) => ($body['credit_party_identifier']['mobile_number'] ?? null) === '+5356085136'),
            )
            ->willReturn($this->acceptedDtoneResponse());

        $this->service->invokeRechargeCommunication(555);
    }
}
