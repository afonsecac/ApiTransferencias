<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderDispatchResolver;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Provider\SelectedDispatch;
use App\Repository\ClientProviderRoutingRepository;
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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationSaleService::processRecharge
 *
 * Cubre la validación síncrona (en admisión, antes de persistir la venta)
 * de qué identificador(es) de destino exige el producto resuelto — ver
 * CommunicationProduct::$requiredIdentifierFields. Confirmado contra el
 * sandbox real de DTOne el 2026-08-31: Nauta WIFI Recharge exige SOLO
 * accountIdentifier, Nauta PLUS SOLO phoneNumber, y Nauta Hogar Plus AMBOS
 * a la vez — todos con el MISMO service/subservice, así que el chequeo
 * tiene que ser contra requiredIdentifierFields, no contra ningún atajo de
 * catálogo. Rechazar aquí, de forma síncrona, evita que una venta admitida
 * sin el dato correcto quede fallando en silencio en el worker async (ver
 * CommunicationSaleServiceAccountIdentifierDispatchTest para el lado del
 * despacho).
 */
class CommunicationSaleServiceRecipientIdentifierValidationTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private BalanceService&MockObject $balanceService;
    private PackageCatalogResolver&MockObject $packageCatalogResolver;
    private ProviderDispatchResolver&MockObject $dispatchResolver;
    private CommunicationSaleService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->balanceService = $this->createMock(BalanceService::class);
        $availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $this->packageCatalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->dispatchResolver = $this->createMock(ProviderDispatchResolver::class);
        $promotionDispatchResolver = $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class);

        $parameters = $this->createMock(ParameterBagInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $historicalSaleService = $this->createMock(HistoricalSaleService::class);
        $notificationCenter = $this->createMock(NotificationCenterService::class);
        $configureSequence = $this->createMock(ConfigureSequenceService::class);
        $configureSequence->method('getLastSequence')->willReturn(1);

        $routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $providerRegistry = new ProviderRegistry([]);
        $providerResolver = new ProviderResolver($sysConfigRepo, $routingRepo, $this->createMock(LoggerInterface::class));
        $providerContextFactory = new ProviderContextFactory($providerResolver);

        $this->service = new CommunicationSaleService(
            $this->em,
            $this->security,
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
            $availabilityService,
            $this->packageCatalogResolver,
            $this->dispatchResolver,
            $promotionDispatchResolver,
            $this->createMock(\App\Service\Provider\SaleProviderFailoverService::class),
        );
    }

    private function account(): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);
        $account->method('getId')->willReturn(99);

        return $account;
    }

    private function catalogPackage(): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName('Nauta')
            ->setDescription('Nauta')
            ->setDestinationAmount(250.0)
            ->setDestinationCurrency('CUP');

        $property = new \ReflectionProperty($package, 'id');
        $property->setAccessible(true);
        $property->setValue($package, 42);

        return $package;
    }

    /**
     * @param list<list<string>> $requiredIdentifierFields
     */
    private function stubAdmission(array $requiredIdentifierFields): CommunicationPackage
    {
        $account = $this->account();
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage();
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($package);
        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $repo],
        ]);

        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getRequiredIdentifierFields')->willReturn($requiredIdentifierFields);

        $this->packageCatalogResolver->method('offerForSale')
            ->willReturn(new ResolvedPackageOffer($package, 9.97, 'USD', PackageOfferSourceEnum::PRODUCT_MAX));
        $this->dispatchResolver->method('select')
            ->willReturn(new SelectedDispatch(CommunicationProviderEnum::DTONE, $product, '35835'));
        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        return $package;
    }

    public function testAcceptsNautaWifiRechargeWithOnlyAccountIdentifier(): void
    {
        $this->stubAdmission([['accountIdentifier']]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setAccountIdentifier('usuario@nauta.com.cu');

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('usuario@nauta.com.cu', $result->getAccountIdentifier());
    }

    public function testRejectsNautaWifiRechargeWithOnlyPhoneNumber(): void
    {
        $this->stubAdmission([['accountIdentifier']]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setPhoneNumber('55501234');

        $this->expectException(MyCurrentException::class);

        $this->service->processRecharge($recharge);
    }

    public function testAcceptsNautaPlusWithOnlyPhoneNumber(): void
    {
        $this->stubAdmission([['phoneNumber']]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setPhoneNumber('55501234');

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('55501234', $result->getPhoneNumber());
    }

    public function testRejectsNautaHogarWithOnlyPhoneNumberWhenBothAreRequiredTogether(): void
    {
        $this->stubAdmission([['phoneNumber', 'accountIdentifier']]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setPhoneNumber('55501234');

        $this->expectException(MyCurrentException::class);

        $this->service->processRecharge($recharge);
    }

    public function testAcceptsNautaHogarWithBothFieldsWhenBothAreRequiredTogether(): void
    {
        $this->stubAdmission([['phoneNumber', 'accountIdentifier']]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setPhoneNumber('55501234');
        $recharge->setAccountIdentifier('usuario@nauta.com.cu');

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('55501234', $result->getPhoneNumber());
        $this->assertSame('usuario@nauta.com.cu', $result->getAccountIdentifier());
    }

    public function testAcceptsEitherAlternativeWhenProductDeclaresMultipleOptions(): void
    {
        $this->stubAdmission([['phoneNumber'], ['accountIdentifier']]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setAccountIdentifier('usuario@nauta.com.cu');

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('usuario@nauta.com.cu', $result->getAccountIdentifier());
    }

    /**
     * Producto sin requiredIdentifierFields declarado (catálogo histórico,
     * incluido ETECSA/Cubacel de siempre) — comportamiento anterior a este
     * fix: se sigue exigiendo phoneNumber.
     */
    public function testLegacyProductWithNoDeclaredFieldsStillRequiresPhoneNumber(): void
    {
        $this->stubAdmission([]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);

        $this->expectException(MyCurrentException::class);

        $this->service->processRecharge($recharge);
    }

    public function testLegacyProductWithNoDeclaredFieldsAcceptsPhoneNumber(): void
    {
        $this->stubAdmission([]);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setPhoneNumber('55501234');

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('55501234', $result->getPhoneNumber());
    }
}
