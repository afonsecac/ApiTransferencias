<?php

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\DTO\ReserveRecharge;
use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationPromotionsRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\BalanceService;
use App\Service\CommunicationSaleService;
use App\Service\ConfigureSequenceService;
use App\Service\HistoricalSaleService;
use App\Service\NotificationCenterService;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use Doctrine\ORM\EntityManagerInterface;
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
 * Cubre exclusivamente el guard de admisión introducido en la Fase 3
 * (resolveAndGuardProvider): el proveedor de la venta se toma del producto,
 * no de la cuenta, y se valida contra ProviderResolver::allowedForClient()
 * antes de admitir la venta. Vía processRecharge() (el más simple de los
 * tres puntos de admisión), ya que no existe una suite completa de
 * CommunicationSaleService.
 */
class CommunicationSaleServiceProviderGuardTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private ClientProviderRoutingRepository&MockObject $routingRepo;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private BalanceService&MockObject $balanceService;
    private CommunicationSaleService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->balanceService = $this->createMock(BalanceService::class);

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

        // ProviderRegistry/ProviderResolver son `final`: se instancian reales
        // con sus propias dependencias mockeadas.
        $providerRegistry = new ProviderRegistry([]);
        $providerResolverLogger = $this->createMock(LoggerInterface::class);
        $providerResolver = new ProviderResolver($this->sysConfigRepo, $this->routingRepo, $providerResolverLogger);
        $providerContextFactory = new ProviderContextFactory($providerResolver);

        $this->service = new CommunicationSaleService(
            $this->em,
            $this->security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $this->sysConfigRepo,
            $serializer,
            $providerRegistry,
            $providerResolver,
            $providerContextFactory,
            $configureSequence,
            $messageBus,
            $historicalSaleService,
            $this->balanceService,
            $notificationCenter,
        );
    }

    /**
     * Deshabilita el dispatch de mensajes (communications.dispatch.enabled='0')
     * para evitar construir SaleRechargeMessage con un id nulo — en este test
     * las entidades nunca pasan por un EntityManager real, así que su id
     * autogenerado nunca se asigna. No es lo que se está probando aquí.
     */
    private function stubDispatchDisabled(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === 'communications.dispatch.enabled' ? '0' : null);
    }

    private function accountWithClient(int $clientId): Account&MockObject
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

    private function packageWithProvider(
        string $provider,
        float $amount = 10.0,
        string $currency = 'USD',
        ?string $packageType = null,
    ): CommunicationClientPackage&MockObject {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getProvider')->willReturn($provider);
        $product->method('getPackageType')->willReturn($packageType);

        $pricePackage = $this->createMock(CommunicationPricePackage::class);
        $pricePackage->method('getProduct')->willReturn($product);

        $package = $this->createMock(CommunicationClientPackage::class);
        $package->method('getPriceClientPackage')->willReturn($pricePackage);
        $package->method('getAmount')->willReturn($amount);
        $package->method('getCurrency')->willReturn($currency);
        $package->method('getPromotionItems')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        return $package;
    }

    private function stubPackageRepository(?CommunicationClientPackage $package): void
    {
        $packageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $packageRepo->method('getPackageById')->willReturn($package);
        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $packageRepo],
        ]);
    }

    public function testAdmitsSaleWhenProductProviderIsAllowedForClient(): void
    {
        // Kill switch en '0' (default cuando no hay filas de routing) => allowedForClient() = [ETECSA].
        $this->stubDispatchDisabled();
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);

        $account = $this->accountWithClient(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->packageWithProvider('ETECSA');
        $this->stubPackageRepository($package);

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('ETECSA', $result->getProvider());
    }

    public function testRejectsSaleWhenProductProviderIsNotAllowedForClient(): void
    {
        $this->stubDispatchDisabled();
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);

        $account = $this->accountWithClient(1);
        $this->security->method('getUser')->willReturn($account);

        // El producto es de DTOne, pero allowedForClient() (sin routing) solo permite ETECSA.
        $package = $this->packageWithProvider('DTONE');
        $this->stubPackageRepository($package);

        $this->em->expects($this->never())->method('persist');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('El paquete pertenece a un proveedor no habilitado para este cliente');

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);

        $this->service->processRecharge($recharge);
    }

    public function testAdmitsDtoneProductWhenClientHasActiveDtoneRouting(): void
    {
        $this->stubDispatchDisabled();
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn(['DTONE']);

        $account = $this->accountWithClient(2);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->packageWithProvider('DTONE');
        $this->stubPackageRepository($package);

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('DTONE', $result->getProvider());
    }

    public function testFallsBackToEtecsaWhenProductHasNoResolvableProvider(): void
    {
        $this->stubDispatchDisabled();
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);

        $account = $this->accountWithClient(1);
        $this->security->method('getUser')->willReturn($account);

        // priceClientPackage sin producto: el guard cae a ETECSA por defecto.
        $pricePackage = $this->createMock(CommunicationPricePackage::class);
        $pricePackage->method('getProduct')->willReturn(null);
        $package = $this->createMock(CommunicationClientPackage::class);
        $package->method('getPriceClientPackage')->willReturn($pricePackage);
        $package->method('getAmount')->willReturn(10.0);
        $package->method('getCurrency')->willReturn('USD');
        $package->method('getPromotionItems')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $this->stubPackageRepository($package);

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);

        $result = $this->service->processRecharge($recharge);

        $this->assertSame(CommunicationProviderEnum::ETECSA->value, $result->getProvider());
    }

    public function testRejectsRechargeWhenProductIsPinPurchase(): void
    {
        $this->stubDispatchDisabled();
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);

        $account = $this->accountWithClient(1);
        $this->security->method('getUser')->willReturn($account);

        // Producto PIN_PURCHASE: nunca vendible como recarga, sin importar
        // que el proveedor esté permitido para el cliente.
        $package = $this->packageWithProvider('ETECSA', packageType: 'FIXED_VALUE_PIN_PURCHASE');
        $this->stubPackageRepository($package);

        $this->em->expects($this->never())->method('persist');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('Este producto se canjea por código y solo puede venderse como paquete, no como recarga');

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);

        $this->service->processRecharge($recharge);
    }

    public function testAdmitsRechargeWhenProductTypeIsFixedValueRecharge(): void
    {
        $this->stubDispatchDisabled();
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);

        $account = $this->accountWithClient(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->packageWithProvider('ETECSA', packageType: 'FIXED_VALUE_RECHARGE');
        $this->stubPackageRepository($package);

        $this->balanceService->method('hasAvailableBalance')->willReturn(true);

        $recharge = new CommunicationSaleRecharge();
        $recharge->setPackageId(1);

        $result = $this->service->processRecharge($recharge);

        $this->assertSame('ETECSA', $result->getProvider());
    }

    public function testProcessReserveAlsoRejectsPinPurchaseProduct(): void
    {
        // El guard debe aplicar igual en processReserve() (recarga futura),
        // no solo en processRecharge() — ambos crean una CommunicationSaleRecharge.
        $this->stubDispatchDisabled();
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);

        $account = $this->accountWithClient(1);
        $this->security->method('getUser')->willReturn($account);

        $package = $this->packageWithProvider('ETECSA', packageType: 'FIXED_VALUE_PIN_PURCHASE');
        $packageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $packageRepo->method('getPackageByIdForReserve')->willReturn($package);

        $promotion = $this->createMock(CommunicationPromotions::class);
        $promotionRepo = $this->createMock(CommunicationPromotionsRepository::class);
        $promotionRepo->method('getFuturePromotionById')->willReturn($promotion);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $packageRepo],
            [CommunicationPromotions::class, $promotionRepo],
        ]);

        $this->em->expects($this->never())->method('persist');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('Este producto se canjea por código y solo puede venderse como paquete, no como recarga');

        $reserve = new ReserveRecharge('5550001234', 1, 1, 'reserve-ctx-1');

        $this->service->processReserve($reserve);
    }
}
