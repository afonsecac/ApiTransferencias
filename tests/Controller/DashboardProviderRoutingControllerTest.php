<?php

namespace App\Tests\Controller;

use App\Controller\DashboardProviderRoutingController;
use App\DTO\CreateProviderRoutingDto;
use App\DTO\Out\ProviderRoutingPreviewOutDto;
use App\DTO\UpdateProviderRoutingDto;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\ProviderRegistry;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
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
    private DashboardProviderRoutingController $controller;

    protected function setUp(): void
    {
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->adminService = $this->createMock(ProviderRoutingAdminService::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);

        $etecsa = $this->createMock(CommunicationProviderInterface::class);
        $etecsa->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);
        $etecsa->method('getCapabilities')->willReturn([ProviderCapabilityEnum::RECHARGE, ProviderCapabilityEnum::PACKAGE_SALE]);

        // ProviderRegistry es `final`: se instancia real, no se dobla.
        $registry = new ProviderRegistry([$etecsa]);

        $this->controller = new DashboardProviderRoutingController(
            $this->routingRepo,
            $this->adminService,
            $registry,
            $this->sysConfigRepo,
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
        $this->assertTrue($data['routingEnabled']);
        $this->assertSame('ETECSA', $data['defaultProvider']);
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
}
