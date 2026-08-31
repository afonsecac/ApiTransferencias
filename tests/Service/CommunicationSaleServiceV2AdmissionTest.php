<?php

namespace App\Tests\Service;

use App\DTO\ReserveRecharge;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSalePackage;
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
use App\Repository\CommunicationPackageRepository;
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
 * @covers \App\Service\CommunicationSaleService
 *
 * Cubre admitV2()/admitV2ForReserve() — único camino de admisión desde la
 * Fase 5 de la deprecación de V1 (admitLegacy() se retiró junto con
 * resolveAndGuardProvider()/assertRechargeableProduct(); el guard de
 * proveedor/despacho vive ahora en ProviderDispatchResolver, ver su propia
 * suite ProviderDispatchResolverTest).
 */
class CommunicationSaleServiceV2AdmissionTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private BalanceService&MockObject $balanceService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private PackageCatalogResolver&MockObject $packageCatalogResolver;
    private ProviderDispatchResolver&MockObject $dispatchResolver;
    private \App\Provider\PromotionProviderDispatchResolver&MockObject $promotionDispatchResolver;
    private CommunicationSaleService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->balanceService = $this->createMock(BalanceService::class);
        $this->availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $this->availabilityService->method('canDispatchTo')->willReturn(false);
        $this->packageCatalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->dispatchResolver = $this->createMock(ProviderDispatchResolver::class);
        $this->promotionDispatchResolver = $this->createMock(\App\Provider\PromotionProviderDispatchResolver::class);

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
            $this->availabilityService,
            $this->packageCatalogResolver,
            $this->dispatchResolver,
            $this->promotionDispatchResolver,
            $this->createMock(\App\Service\Provider\SaleProviderFailoverService::class),
        );
    }

    private function account(int $clientId): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn($clientId);

        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);
        $account->method('getId')->willReturn(99);

        return $account;
    }

    private function catalogPackage(int $id = 42, float $amount = 500.0, string $currency = 'CUP'): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName('Recarga V2')
            ->setDescription('Recarga V2')
            ->setDestinationAmount($amount)
            ->setDestinationCurrency($currency);

        $property = new \ReflectionProperty($package, 'id');
        $property->setAccessible(true);
        $property->setValue($package, $id);

        return $package;
    }

    private function stubCommunicationPackageRepo(?CommunicationPackage $package): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($package);
        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $repo],
        ]);
    }

    public function testProcessRechargeUsesV2Admission(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage();
        $this->stubCommunicationPackageRepo($package);

        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('7854-250');

        $this->packageCatalogResolver->expects($this->once())
            ->method('offerForSale')
            ->with($package, $account)
            ->willReturn(new ResolvedPackageOffer($package, 8.5, 'USD', PackageOfferSourceEnum::PRODUCT_MAX));
        $this->dispatchResolver->expects($this->once())
            ->method('select')
            ->with($account, $package, 'recharge')
            ->willReturn(new SelectedDispatch(CommunicationProviderEnum::CSQ, $product, '7854-250'));

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);
        $recharge->setPhoneNumber('5550001234');

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('CSQ', $result->getProvider());
        $this->assertSame(8.5, $result->getAmount());
        $this->assertSame('USD', $result->getCurrency());
        $this->assertSame($package, $result->getCatalogPackage());
        $this->assertSame($product, $result->getDispatchProduct());
        $this->assertSame('7854-250', $result->getDispatchExternalRef());
        $this->assertSame(500.0, $result->getDestinationAmount());
        $this->assertSame('CUP', $result->getDestinationCurrency());
        $this->assertNull($result->getPackage());
    }

    public function testProcessRechargeThrowsCom003WhenPackageDoesNotExist(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);
        $this->stubCommunicationPackageRepo(null);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('The package don\'t exist');

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(999);

        $this->service->processRecharge($recharge);
    }

    public function testProcessRechargeThrowsCom003WhenPackageIsInactive(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage()->setIsActive(false);
        $this->stubCommunicationPackageRepo($package);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('The package don\'t exist');

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);

        $this->service->processRecharge($recharge);
    }

    public function testProcessRechargeThrowsCom003WhenPackageWindowHasNotStartedYet(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage()->setActiveStartAt(new \DateTimeImmutable('+1 day'));
        $this->stubCommunicationPackageRepo($package);

        $this->expectException(MyCurrentException::class);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(42);

        $this->service->processRecharge($recharge);
    }

    public function testProcessRechargePropagatesPackageNotVisibleFromCatalogResolver(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage();
        $this->stubCommunicationPackageRepo($package);

        $this->packageCatalogResolver->method('offerForSale')
            ->willThrowException(new MyCurrentException('PACKAGE_NOT_VISIBLE_FOR_CLIENT', 'Este paquete no está disponible para este cliente', 409));

        $this->em->expects($this->never())->method('persist');

        try {
            $recharge = new CommunicationSaleRecharge();
            $recharge->setPackageId(42);
            $this->service->processRecharge($recharge);
            $this->fail('Se esperaba MyCurrentException');
        } catch (MyCurrentException $e) {
            $this->assertSame('PACKAGE_NOT_VISIBLE_FOR_CLIENT', $e->getCodeWork());
        }
    }

    public function testProcessRechargePropagatesPackageNotDispatchableFromDispatchResolver(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage();
        $this->stubCommunicationPackageRepo($package);

        $this->packageCatalogResolver->method('offerForSale')
            ->willReturn(new ResolvedPackageOffer($package, 8.5, 'USD', PackageOfferSourceEnum::PRODUCT_MAX));
        $this->dispatchResolver->method('select')
            ->willThrowException(new MyCurrentException('PACKAGE_NOT_DISPATCHABLE', 'Ningún proveedor disponible puede despachar este paquete', 409));

        $this->em->expects($this->never())->method('persist');

        try {
            $recharge = new CommunicationSaleRecharge();
            $recharge->setPackageId(42);
            $this->service->processRecharge($recharge);
            $this->fail('Se esperaba MyCurrentException');
        } catch (MyCurrentException $e) {
            $this->assertSame('PACKAGE_NOT_DISPATCHABLE', $e->getCodeWork());
        }
    }

    public function testExecuteSaleUsesV2Admission(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('7951-2200');

        $commercialOffice = $this->createMock(CommunicationOffice::class);
        $nationality = $this->createMock(CommunicationNationality::class);
        $officeRepo = $this->createMock(EntityRepository::class);
        $officeRepo->method('findOneBy')->willReturn($commercialOffice);
        $nationalityRepo = $this->createMock(EntityRepository::class);
        $nationalityRepo->method('findOneBy')->willReturn($nationality);
        $packageRepo = $this->createMock(EntityRepository::class);
        $packageRepo->method('find')->willReturn($package);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $packageRepo],
            [CommunicationOffice::class, $officeRepo],
            [CommunicationNationality::class, $nationalityRepo],
        ]);

        $this->packageCatalogResolver->method('offerForSale')
            ->willReturn(new ResolvedPackageOffer($package, 15.0, 'USD', PackageOfferSourceEnum::PRODUCT_MAX));
        $this->dispatchResolver->method('select')
            ->with($account, $package, 'sale')
            ->willReturn(new SelectedDispatch(CommunicationProviderEnum::DTONE, $product, '7951-2200'));

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $sale = new CommunicationSalePackage();
        $sale->setPackageId(42);
        $sale->nationalityId = 1;
        $sale->commercialOfficeId = 1;

        $result = $this->service->executeSale($sale);

        $this->assertSame('DTONE', $result->getProvider());
        $this->assertSame($package, $result->getCatalogPackage());
        $this->assertSame('7951-2200', $result->getDispatchExternalRef());
        $this->assertNull($result->getPackage());
    }

    public function testProcessReserveUsesAdmitV2ForReserveWhenPackageIsFuture(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $promotion = $this->createMock(CommunicationPromotions::class);
        $package = $this->catalogPackage()->setPromotion($promotion);

        $catalogPackageRepo = $this->createMock(CommunicationPackageRepository::class);
        $catalogPackageRepo->expects($this->once())
            ->method('findFutureForReserve')
            ->with(42, 93)
            ->willReturn($package);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $catalogPackageRepo],
        ]);

        $product = $this->createMock(CommunicationProduct::class);

        $this->packageCatalogResolver->expects($this->once())
            ->method('offerForSale')
            ->with($package, $account)
            ->willReturn(new ResolvedPackageOffer($package, 8.5, 'USD', PackageOfferSourceEnum::PRODUCT_MAX));
        $this->promotionDispatchResolver->expects($this->once())
            ->method('select')
            ->with($account, $promotion)
            ->willReturn(new SelectedDispatch(CommunicationProviderEnum::CSQ, $product, '7854-250'));
        // Reserve resuelve el proveedor a nivel de PROMOCIÓN — el dispatch
        // por paquete (usado en la compra directa, admitV2()) nunca se toca
        // aquí.
        $this->dispatchResolver->expects($this->never())->method('select');

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $reserve = new ReserveRecharge('5550001234', 93, 42, 'reserve-ctx-v2-future');

        $result = $this->service->processReserve($reserve);

        $this->assertSame('CSQ', $result->getProvider());
        $this->assertSame($package, $result->getCatalogPackage());
        $this->assertSame(8.5, $result->getAmount());
        $this->assertSame('USD', $result->getCurrency());
        $this->assertSame('7854-250', $result->getDispatchExternalRef());
        $this->assertSame(500.0, $result->getDestinationAmount());
        $this->assertSame('CUP', $result->getDestinationCurrency());
    }

    public function testProcessReserveThrowsCom007WhenNoFuturePromotionPackageIsFound(): void
    {
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $catalogPackageRepo = $this->createMock(CommunicationPackageRepository::class);
        $catalogPackageRepo->method('findFutureForReserve')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $catalogPackageRepo],
        ]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('The promotion is not active to reserves');

        $reserve = new ReserveRecharge('5550001234', 93, 42, 'reserve-ctx-v2-missing');

        $this->service->processReserve($reserve);
    }

    public function testProcessReserveThrowsCom007WhenFuturePackageHasNoPromotion(): void
    {
        // No debería poder pasar en la práctica (findFutureForReserve() hace
        // INNER JOIN con la promoción), pero el chequeo explícito documenta
        // el invariante y evita un TypeError si algún día deja de serlo.
        $account = $this->account(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->catalogPackage();

        $catalogPackageRepo = $this->createMock(CommunicationPackageRepository::class);
        $catalogPackageRepo->method('findFutureForReserve')->willReturn($package);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $catalogPackageRepo],
        ]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('The promotion is not active to reserves');

        $reserve = new ReserveRecharge('5550001234', 93, 42, 'reserve-ctx-v2-no-promo');

        $this->service->processReserve($reserve);
    }
}
