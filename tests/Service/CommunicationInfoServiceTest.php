<?php

namespace App\Tests\Service;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\DTO\RequestInfo;
use App\Entity\Account;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\User;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\PackageSaleProviderInterface;
use App\Provider\Contract\PackageSaleRequest;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderDispatchResult;
use App\Provider\Contract\ProviderStatusQuery;
use App\Provider\Contract\ProviderStatusResult;
use App\Provider\Contract\RechargeProviderInterface;
use App\Provider\Contract\RechargeRequest;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationSalePackageRepository;
use App\Repository\CommunicationSaleRechargeRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommunicationInfoService;
use App\Service\HistoricalSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\CommunicationInfoService
 *
 * Fase 3: querySale() debe consultar el estado a través del proveedor real
 * de la venta (snapshot en communication_sale_info.provider), no siempre
 * ETECSA vía EtecsaGatewayClient directo.
 */
class CommunicationInfoServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private HistoricalSaleService&MockObject $historicalSaleService;
    private FakeCommunicationProvider $fakeProvider;
    private CommunicationInfoService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->historicalSaleService = $this->createMock(HistoricalSaleService::class);

        $parameters = $this->createMock(ParameterBagInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $this->fakeProvider = new FakeCommunicationProvider();

        // ProviderRegistry/ProviderResolver/ProviderContextFactory son `final`:
        // se instancian reales con sus propias dependencias mockeadas.
        $providerRegistry = new ProviderRegistry([$this->fakeProvider]);
        $routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $providerResolverLogger = $this->createMock(LoggerInterface::class);
        $providerResolver = new ProviderResolver($sysConfigRepo, $routingRepo, $providerResolverLogger);
        $providerContextFactory = new ProviderContextFactory($providerResolver);

        $this->service = new CommunicationInfoService(
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
            $providerContextFactory,
            $this->historicalSaleService,
        );
    }

    private function stubAdmin(): void
    {
        $admin = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($admin);
        $this->security->method('isGranted')->willReturn(true);
    }

    private function accountWithEnvironment(): Account
    {
        $account = new Account();

        return $account;
    }

    private function requestInfo(int $internalTxId, string $type = 'RE'): RequestInfo
    {
        return new RequestInfo(type: $type, clientTxId: null, internalTxId: $internalTxId);
    }

    /**
     * `id` no tiene setter público (autogenerado por Doctrine): se asigna
     * por reflexión para simular una entidad ya persistida.
     */
    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    public function testThrowsAccessDeniedWhenUserIsNull(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedException::class);

        $this->service->querySale($this->requestInfo(1));
    }

    public function testThrowsAccessDeniedWhenNotAdmin(): void
    {
        $admin = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($admin);
        $this->security->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        $this->service->querySale($this->requestInfo(1));
    }

    public function testThrowsWhenOperationNotFound(): void
    {
        $this->stubAdmin();
        $repo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(CommunicationSaleRecharge::class)->willReturn($repo);

        $this->expectException(EntityNotFoundException::class);

        $this->service->querySale($this->requestInfo(1));
    }

    public function testUsesRechargeProviderInterfaceForRecharges(): void
    {
        $this->stubAdmin();

        $sale = new CommunicationSaleRecharge();
        $this->assignId($sale, 1);
        $sale->setTransactionId('TX-1');
        $sale->setClientTransactionId('CTX-1');
        $sale->setCreatedAt(new \DateTimeImmutable('now'));
        $sale->setTenant($this->accountWithEnvironment());
        $sale->setState(CommunicationStateEnum::PENDING);
        $sale->setProvider(CommunicationProviderEnum::ETECSA->value);

        $repo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $repo->method('findOneBy')->willReturn($sale);
        $this->em->method('getRepository')->with(CommunicationSaleRecharge::class)->willReturn($repo);

        $this->historicalSaleService->expects($this->once())
            ->method('createHistoricalCommunication')
            ->with($this->anything(), CommunicationStateEnum::PENDING, ['raw' => 'recharge-status']);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->querySale($this->requestInfo(1));

        $this->assertSame(['raw' => 'recharge-status'], $result);
        $this->assertSame(1, $this->fakeProvider->rechargeStatusCalls);
        $this->assertSame(0, $this->fakeProvider->packageStatusCalls);
    }

    public function testUsesPackageSaleProviderInterfaceForPackages(): void
    {
        $this->stubAdmin();

        $sale = new CommunicationSalePackage();
        $this->assignId($sale, 2);
        $sale->setTransactionId('TX-2');
        $sale->setClientTransactionId('CTX-2');
        $sale->setCreatedAt(new \DateTimeImmutable('now'));
        $sale->setTenant($this->accountWithEnvironment());
        $sale->setState(CommunicationStateEnum::PENDING);
        $sale->setProvider(CommunicationProviderEnum::ETECSA->value);

        $repo = $this->createMock(CommunicationSalePackageRepository::class);
        $repo->method('findOneBy')->willReturn($sale);
        $this->em->method('getRepository')->with(CommunicationSalePackage::class)->willReturn($repo);

        $result = $this->service->querySale($this->requestInfo(1, 'SA'));

        $this->assertSame(['raw' => 'package-status'], $result);
        $this->assertSame(0, $this->fakeProvider->rechargeStatusCalls);
        $this->assertSame(1, $this->fakeProvider->packageStatusCalls);
    }

    public function testReturnsNullWithoutQueryingProviderWhenTenantIsNull(): void
    {
        $this->stubAdmin();

        $sale = new CommunicationSaleRecharge();
        $sale->setTransactionId('TX-3');
        $sale->setClientTransactionId('CTX-3');
        $sale->setCreatedAt(new \DateTimeImmutable('now'));
        $sale->setState(CommunicationStateEnum::PENDING);

        $repo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $repo->method('findOneBy')->willReturn($sale);
        $this->em->method('getRepository')->with(CommunicationSaleRecharge::class)->willReturn($repo);

        $this->historicalSaleService->expects($this->never())->method('createHistoricalCommunication');

        $result = $this->service->querySale($this->requestInfo(1));

        $this->assertNull($result);
        $this->assertSame(0, $this->fakeProvider->rechargeStatusCalls);
    }

    public function testThrowsWhenOperationIsOlderThanSevenDays(): void
    {
        $this->stubAdmin();

        $sale = new CommunicationSaleRecharge();
        $sale->setTransactionId('TX-4');
        $sale->setClientTransactionId('CTX-4');
        $sale->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $sale->setTenant($this->accountWithEnvironment());

        $repo = $this->createMock(CommunicationSaleRechargeRepository::class);
        $repo->method('findOneBy')->willReturn($sale);
        $this->em->method('getRepository')->with(CommunicationSaleRecharge::class)->willReturn($repo);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The operation is older than 7 days and not available to query');

        $this->service->querySale($this->requestInfo(1));
    }
}

/**
 * Doble de proveedor implementando tanto RechargeProviderInterface como
 * PackageSaleProviderInterface, para verificar que querySale() elige la
 * interfaz correcta según la clase concreta de la venta.
 */
final class FakeCommunicationProvider implements CommunicationProviderInterface, RechargeProviderInterface, PackageSaleProviderInterface
{
    public int $rechargeStatusCalls = 0;
    public int $packageStatusCalls = 0;

    public function getCode(): CommunicationProviderEnum
    {
        return CommunicationProviderEnum::ETECSA;
    }

    /**
     * @return list<ProviderCapabilityEnum>
     */
    public function getCapabilities(): array
    {
        return [ProviderCapabilityEnum::RECHARGE, ProviderCapabilityEnum::PACKAGE_SALE];
    }

    public function getConfigSchema(): array
    {
        return [];
    }

    public function recharge(ProviderContext $context, RechargeRequest $request): ProviderDispatchResult
    {
        return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED);
    }

    public function fetchRechargeStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        $this->rechargeStatusCalls++;

        return new ProviderStatusResult(outcome: ProviderOutcomeEnum::PENDING, raw: ['raw' => 'recharge-status']);
    }

    public function sellPackage(ProviderContext $context, PackageSaleRequest $request): ProviderDispatchResult
    {
        return new ProviderDispatchResult(outcome: ProviderOutcomeEnum::ACCEPTED);
    }

    public function fetchPackageSaleStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult
    {
        $this->packageStatusCalls++;

        return new ProviderStatusResult(outcome: ProviderOutcomeEnum::PENDING, raw: ['raw' => 'package-status']);
    }
}
