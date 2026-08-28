<?php

namespace App\Tests\Controller;

use App\Controller\DashboardProviderRoutingController;
use App\DTO\CreateProviderRoutingDto;
use App\DTO\Out\ProviderRoutingPreviewOutDto;
use App\DTO\SetProviderActiveDto;
use App\DTO\UpdateProviderCredentialsDto;
use App\DTO\UpdateProviderRoutingDto;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\ProviderRegistry;
use App\Entity\CurrencyExchangeRate;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CurrencyExchangeRateRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\CurrencyExchangeRateSyncService;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\Provider\ProviderConnectionTestService;
use App\Provider\ProviderCredentialsResolver;
use App\Service\Provider\ProviderCredentialsAdminService;
use App\Service\ProviderRoutingAdminService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\DashboardProviderRoutingController
 */
class DashboardProviderRoutingControllerTest extends TestCase
{
    private ClientProviderRoutingRepository&MockObject $routingRepo;
    private ProviderRoutingAdminService&MockObject $adminService;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private CurrencyExchangeRateSyncService&MockObject $exchangeRateSyncService;
    private CurrencyExchangeRateRepository&MockObject $exchangeRateRepo;
    private ProviderCredentialsAdminService&MockObject $credentialsAdminService;
    private ProviderConnectionTestService&MockObject $connectionTestService;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private DashboardProviderRoutingController $controller;

    protected function setUp(): void
    {
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->adminService = $this->createMock(ProviderRoutingAdminService::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->exchangeRateSyncService = $this->createMock(CurrencyExchangeRateSyncService::class);
        $this->exchangeRateRepo = $this->createMock(CurrencyExchangeRateRepository::class);
        $this->credentialsAdminService = $this->createMock(ProviderCredentialsAdminService::class);
        $this->connectionTestService = $this->createMock(ProviderConnectionTestService::class);
        $this->availabilityService = $this->createMock(ProviderAvailabilityService::class);

        $etecsa = $this->createMock(CommunicationProviderInterface::class);
        $etecsa->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);
        $etecsa->method('getCapabilities')->willReturn([ProviderCapabilityEnum::RECHARGE, ProviderCapabilityEnum::PACKAGE_SALE]);

        // ProviderRegistry y ProviderCredentialsResolver son `final`: se instancian reales, no se doblan.
        $registry = new ProviderRegistry([$etecsa]);
        $credentialsResolver = new ProviderCredentialsResolver($this->sysConfigRepo, $registry);

        $this->controller = new DashboardProviderRoutingController(
            $this->routingRepo,
            $this->adminService,
            $registry,
            $this->sysConfigRepo,
            $this->exchangeRateSyncService,
            $this->exchangeRateRepo,
            $this->credentialsAdminService,
            $this->connectionTestService,
            $this->availabilityService,
            $credentialsResolver,
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    private function routingMock(int $id, string $provider = 'ETECSA', bool $isActive = true): ClientProviderRouting&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        $client->method('getCompanyName')->willReturn('AWS Malta Ltd');

        $routing = $this->createMock(ClientProviderRouting::class);
        $routing->method('getId')->willReturn($id);
        $routing->method('getClient')->willReturn($client);
        $routing->method('getEnvironment')->willReturn(null);
        $routing->method('getSaleType')->willReturn(null);
        $routing->method('getProvider')->willReturn($provider);
        $routing->method('getFallbackProvider')->willReturn(null);
        $routing->method('isActive')->willReturn($isActive);
        $routing->method('getNotes')->willReturn(null);
        $routing->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-08-01'));
        $routing->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2026-08-01'));

        return $routing;
    }

    // ---- listProviders ----

    public function testListProvidersReturnsRegisteredProvidersAndFlags(): void
    {
        $this->sysConfigRepo->method('findCachedValue')->willReturn(null);

        $response = $this->controller->listProviders();
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['providers']);
        $this->assertSame('ETECSA', $data['providers'][0]['code']);
        $this->assertTrue($data['providers'][0]['enabledTest']);
        $this->assertTrue($data['providers'][0]['enabledProd']);
        $this->assertTrue($data['routingEnabled']);
        $this->assertSame('ETECSA', $data['defaultProvider']);
    }

    public function testListProvidersMarksProviderDisabledForEnvironmentWithoutCredentials(): void
    {
        // Sin claves configuradas para PROD (findCachedValue devuelve null
        // salvo el interruptor manual, que no está apagado) — pero
        // getConfigSchema() del stub por defecto es [], así que forzamos un
        // campo requerido para que isFullyConfigured() distinga TEST/PROD.
        $etecsaWithSchema = $this->createMock(CommunicationProviderInterface::class);
        $etecsaWithSchema->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);
        $etecsaWithSchema->method('getCapabilities')->willReturn([]);
        $field = new \App\Provider\Contract\ProviderConfigField('apiKey', 'API Key', true, true);
        $etecsaWithSchema->method('getConfigSchema')->willReturn([$field]);

        $registry = new ProviderRegistry([$etecsaWithSchema]);
        $credentialsResolver = new ProviderCredentialsResolver($this->sysConfigRepo, $registry);
        $this->controller = new DashboardProviderRoutingController(
            $this->routingRepo,
            $this->adminService,
            $registry,
            $this->sysConfigRepo,
            $this->exchangeRateSyncService,
            $this->exchangeRateRepo,
            $this->credentialsAdminService,
            $this->connectionTestService,
            $this->availabilityService,
            $credentialsResolver,
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);

        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === 'provider.etecsa.test.apiKey' ? 'secret' : null);

        $response = $this->controller->listProviders();
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['providers'][0]['enabledTest']);
        $this->assertFalse($data['providers'][0]['enabledProd']);
    }

    public function testListProvidersReflectsKillSwitchDisabled(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === 'communications.provider.routing.enabled' ? '0' : null);

        $response = $this->controller->listProviders();
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['routingEnabled']);
    }

    // ---- list ----

    public function testListReturnsPaginatedResults(): void
    {
        $routing = $this->routingMock(1);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$routing]);
        $qb->method('getQuery')->willReturn($query);

        $this->routingRepo->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->list(new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['currentPage']);
        $this->assertFalse($data['hasNext']);
        $this->assertCount(1, $data['results']);
        $this->assertSame('AWS Malta Ltd', $data['results'][0]['clientName']);
        $this->assertSame('ETECSA', $data['results'][0]['provider']);
    }

    // ---- show ----

    public function testShowReturnsNotFoundWhenMissing(): void
    {
        $this->routingRepo->method('find')->with(999)->willReturn(null);

        $response = $this->controller->show(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testShowReturnsSerializedRouting(): void
    {
        $this->routingRepo->method('find')->with(1)->willReturn($this->routingMock(1, 'DTONE'));

        $response = $this->controller->show(1);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(1, $data['id']);
        $this->assertSame('DTONE', $data['provider']);
    }

    public function testShowSerializesServiceCategory(): void
    {
        $routing = $this->routingMock(1, 'DTONE');
        $routing->method('getServiceName')->willReturn('Mobile');
        $routing->method('getSubserviceName')->willReturn('AIRTIME');
        $this->routingRepo->method('find')->with(1)->willReturn($routing);

        $response = $this->controller->show(1);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('Mobile', $data['serviceName']);
        $this->assertSame('AIRTIME', $data['subserviceName']);
    }

    // ---- create ----

    public function testCreateReturnsCreatedRouting(): void
    {
        $dto = new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE');
        $this->adminService->method('create')->with($dto)->willReturn($this->routingMock(5, 'DTONE'));

        $response = $this->controller->create($dto);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame('DTONE', $data['provider']);
    }

    public function testCreateReturnsErrorFromService(): void
    {
        $dto = new CreateProviderRoutingDto(clientId: 999, provider: 'DTONE');
        $this->adminService->method('create')
            ->willThrowException(new MyCurrentException('CLIENT_NOT_FOUND', 'Cliente no encontrado', Response::HTTP_NOT_FOUND));

        $response = $this->controller->create($dto);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('Cliente no encontrado', $data['error']['message']);
    }

    // ---- update ----

    public function testUpdateReturnsNotFoundWhenMissing(): void
    {
        $this->routingRepo->method('find')->with(999)->willReturn(null);

        $response = $this->controller->update(999, new UpdateProviderRoutingDto());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUpdateReturnsUpdatedRouting(): void
    {
        $existing = $this->routingMock(1, 'ETECSA');
        $updated = $this->routingMock(1, 'DTONE');
        $dto = new UpdateProviderRoutingDto(provider: 'DTONE');

        $this->routingRepo->method('find')->with(1)->willReturn($existing);
        $this->adminService->method('update')->with($existing, $dto)->willReturn($updated);

        $response = $this->controller->update(1, $dto);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('DTONE', $data['provider']);
    }

    // ---- toggle ----

    public function testToggleReturnsNotFoundWhenMissing(): void
    {
        $this->routingRepo->method('find')->with(999)->willReturn(null);

        $response = $this->controller->toggle(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testToggleReturnsToggledState(): void
    {
        $routing = $this->routingMock(1, 'ETECSA', isActive: false);
        $this->routingRepo->method('find')->with(1)->willReturn($routing);
        $this->adminService->method('toggle')->with($routing)->willReturn($routing);

        $response = $this->controller->toggle(1);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(1, $data['id']);
        $this->assertFalse($data['isActive']);
    }

    public function testToggleReturnsErrorWhenScopeTaken(): void
    {
        $routing = $this->routingMock(1, 'ETECSA', isActive: false);
        $this->routingRepo->method('find')->with(1)->willReturn($routing);
        $this->adminService->method('toggle')
            ->willThrowException(new MyCurrentException('PROVIDER_ROUTING_DUPLICATE', 'Ya existe un enrutado activo.', Response::HTTP_CONFLICT));

        $response = $this->controller->toggle(1);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    // ---- delete ----

    public function testDeleteReturnsNotFoundWhenMissing(): void
    {
        $this->routingRepo->method('find')->with(999)->willReturn(null);

        $response = $this->controller->delete(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteReturnsDeletedTrue(): void
    {
        $routing = $this->routingMock(1);
        $this->routingRepo->method('find')->with(1)->willReturn($routing);
        $this->adminService->expects($this->once())->method('delete')->with($routing);

        $response = $this->controller->delete(1);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['deleted']);
    }

    // ---- preview ----

    public function testPreviewReturnsBadRequestWhenMissingParams(): void
    {
        $response = $this->controller->preview(new Request());

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testPreviewReturnsPreviewData(): void
    {
        // Proveedor propuesto = ETECSA (el único registrado en el setUp): así se
        // aísla esta aserción de la de proposedProviderUnregistered, cubierta aparte.
        $preview = new ProviderRoutingPreviewOutDto();
        $preview->currentEffectiveProvider = 'ETECSA';
        $preview->proposedEffectiveProvider = 'ETECSA';
        $preview->pendingSalesCount = 4;

        $this->adminService->method('preview')->with(1, null, null, 'ETECSA')->willReturn($preview);

        $request = new Request(['clientId' => '1', 'provider' => 'ETECSA']);
        $response = $this->controller->preview($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('ETECSA', $data['currentEffectiveProvider']);
        $this->assertSame('ETECSA', $data['proposedEffectiveProvider']);
        $this->assertSame(4, $data['pendingSalesCount']);
        $this->assertFalse($data['proposedProviderUnregistered']);
    }

    public function testPreviewFlagsUnregisteredProposedProvider(): void
    {
        $preview = new ProviderRoutingPreviewOutDto();
        $preview->currentEffectiveProvider = 'ETECSA';
        $preview->proposedEffectiveProvider = 'DTONE';

        $this->adminService->method('preview')->willReturn($preview);

        // El mock de ProviderRegistry en setUp() solo registra ETECSA -> DTONE no está registrado.
        $request = new Request(['clientId' => '1', 'provider' => 'DTONE']);
        $response = $this->controller->preview($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['proposedProviderUnregistered']);
    }

    public function testPreviewReturnsErrorFromService(): void
    {
        $this->adminService->method('preview')
            ->willThrowException(new MyCurrentException('CLIENT_NOT_FOUND', 'Cliente no encontrado', Response::HTTP_NOT_FOUND));

        $request = new Request(['clientId' => '999', 'provider' => 'DTONE']);
        $response = $this->controller->preview($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSyncExchangeRatesReturnsResult(): void
    {
        $this->exchangeRateSyncService->method('sync')
            ->willReturn(new \App\Service\Provider\ExchangeRateSyncResult(29, '2026-07-31', 'EUR'));

        $response = $this->controller->syncExchangeRates();
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(29, $data['created']);
        $this->assertSame('2026-07-31', $data['rateDate']);
        $this->assertSame('EUR', $data['baseCurrency']);
    }

    public function testSyncExchangeRatesReturnsBadGatewayOnFailure(): void
    {
        $this->exchangeRateSyncService->method('sync')
            ->willThrowException(new \RuntimeException('Frankfurter inalcanzable'));

        $response = $this->controller->syncExchangeRates();

        $this->assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
    }

    private function exchangeRate(string $base, string $target, float $rate, string $rateDate): CurrencyExchangeRate
    {
        return (new CurrencyExchangeRate())
            ->setBaseCurrency($base)
            ->setTargetCurrency($target)
            ->setRate($rate)
            ->setRateDate(new \DateTimeImmutable($rateDate))
            ->setFetchedAt(new \DateTimeImmutable($rateDate . ' 00:05:00'));
    }

    public function testExchangeRatesHistoryReturnsPaginatedResults(): void
    {
        $rows = [
            $this->exchangeRate('EUR', 'USD', 1.1, '2026-07-31'),
            $this->exchangeRate('EUR', 'GBP', 0.88, '2026-07-31'),
        ];

        $this->exchangeRateRepo->method('countHistory')->with(null)->willReturn(58);
        $this->exchangeRateRepo->method('findHistory')->with(null, 21, 0)->willReturn($rows);

        $response = $this->controller->exchangeRatesHistory(new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertSame(58, $data['total']);
        $this->assertFalse($data['hasNext']);
        $this->assertCount(2, $data['results']);
        $this->assertSame('USD', $data['results'][0]['targetCurrency']);
        $this->assertSame(1.1, $data['results'][0]['rate']);
        $this->assertSame('2026-07-31', $data['results'][0]['rateDate']);
    }

    public function testExchangeRatesHistoryFiltersByTargetCurrencyUppercased(): void
    {
        $this->exchangeRateRepo->expects($this->once())
            ->method('findHistory')
            ->with('GBP', $this->anything(), $this->anything())
            ->willReturn([]);
        $this->exchangeRateRepo->method('countHistory')->with('GBP')->willReturn(0);

        $this->controller->exchangeRatesHistory(new Request(['targetCurrency' => 'gbp']));
    }

    // ---- credentialsStatus ----

    public function testCredentialsStatusReturns404ForUnknownProvider(): void
    {
        $response = $this->controller->credentialsStatus('UNKNOWN');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCredentialsStatusDelegatesToService(): void
    {
        $status = [
            'test' => ['baseUrl' => 'https://preprod.example', 'hasApiKey' => true, 'hasApiSecret' => false],
            'prod' => ['baseUrl' => null, 'hasApiKey' => false, 'hasApiSecret' => false],
        ];
        $this->credentialsAdminService->expects($this->once())
            ->method('getStatus')
            ->with(CommunicationProviderEnum::DTONE)
            ->willReturn($status);

        $response = $this->controller->credentialsStatus('DTONE');
        $data = json_decode($response->getContent(), true);

        $this->assertSame($status, $data);
    }

    // ---- updateCredentials ----

    public function testUpdateCredentialsReturns404ForUnknownProvider(): void
    {
        $response = $this->controller->updateCredentials('UNKNOWN', 'TEST', new UpdateProviderCredentialsDto());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUpdateCredentialsReturnsErrorFromService(): void
    {
        $this->credentialsAdminService->method('upsert')
            ->willThrowException(new MyCurrentException('SYS_CONFIG_ENCRYPTION_KEY_MISSING', 'falta la clave', 500));

        $response = $this->controller->updateCredentials('DTONE', 'TEST', new UpdateProviderCredentialsDto(['api_key' => 'abc']));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testUpdateCredentialsReturnsUpdatedStatusOnSuccess(): void
    {
        $dto = new UpdateProviderCredentialsDto(['base_url' => 'https://x.example']);
        $this->credentialsAdminService->expects($this->once())
            ->method('upsert')
            ->with(CommunicationProviderEnum::DTONE, 'TEST', $dto);
        $this->credentialsAdminService->method('getStatus')->willReturn([
            'test' => ['base_url' => ['key' => 'base_url', 'label' => 'URL base', 'required' => true, 'secret' => false, 'value' => 'https://x.example', 'configured' => true]],
            'prod' => [],
            'isFullyConfiguredTest' => false,
            'isFullyConfiguredProd' => false,
        ]);

        $response = $this->controller->updateCredentials('DTONE', 'TEST', $dto);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('https://x.example', $data['base_url']['value']);
    }

    // ---- setActive ----

    public function testSetActiveReturns404ForUnknownProvider(): void
    {
        $response = $this->controller->setActive('UNKNOWN', 'TEST', new SetProviderActiveDto(false));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSetActiveDelegatesToServiceAndReturnsUpdatedStatus(): void
    {
        $this->availabilityService->expects($this->once())
            ->method('setManual')
            ->with(CommunicationProviderEnum::DTONE, 'PROD', false);
        $this->credentialsAdminService->method('getStatus')->willReturn([
            'test' => [],
            'prod' => [],
            'isFullyConfiguredTest' => false,
            'isFullyConfiguredProd' => true,
            'activeTest' => true,
            'activeProd' => false,
        ]);

        $response = $this->controller->setActive('DTONE', 'PROD', new SetProviderActiveDto(false));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['activeProd']);
    }

    // ---- testConnection ----

    public function testTestConnectionReturns404ForUnknownProvider(): void
    {
        $response = $this->controller->testConnection('UNKNOWN', new Request());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testTestConnectionRejectsInvalidEnvironmentType(): void
    {
        $response = $this->controller->testConnection('DTONE', new Request(['environmentType' => 'STAGING']));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testTestConnectionDefaultsToProd(): void
    {
        $this->connectionTestService->expects($this->once())
            ->method('test')
            ->with(CommunicationProviderEnum::DTONE, 'PROD')
            ->willReturn(['success' => true, 'amounts' => ['USD' => 100.0], 'fetchedAt' => '2026-08-01T00:00:00+00:00']);

        $response = $this->controller->testConnection('DTONE', new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(100.0, $data['amounts']['USD']);
    }

    public function testTestConnectionReturnsFailureResultAsIs(): void
    {
        $this->connectionTestService->method('test')->willReturn(['success' => false, 'message' => 'boom']);

        $response = $this->controller->testConnection('ETECSA', new Request(['environmentType' => 'test']));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertSame('boom', $data['message']);
    }
}
