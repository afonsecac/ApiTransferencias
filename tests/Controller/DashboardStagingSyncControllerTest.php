<?php

namespace App\Tests\Controller;

use App\Controller\DashboardStagingSyncController;
use App\Entity\StagingSyncRun;
use App\Enums\StagingSyncStatusEnum;
use App\Exception\MyCurrentException;
use App\Service\StagingSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @covers \App\Controller\DashboardStagingSyncController
 */
class DashboardStagingSyncControllerTest extends TestCase
{
    private StagingSyncService&MockObject $service;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->service = $this->createMock(StagingSyncService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    private function controller(string $deploymentStage): DashboardStagingSyncController
    {
        $controller = new DashboardStagingSyncController($this->service, $this->em, $deploymentStage);

        // trigger() llama a getUser(), que AbstractController resuelve vía
        // container->has/get('security.token_storage') — sin esto lanza
        // "SecurityBundle no registrado" incluso con un usuario mockeado.
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn (string $id) => $id === 'security.token_storage',
        );
        $container->method('get')->willReturnCallback(
            static fn (string $id) => $id === 'security.token_storage' ? $tokenStorage : null,
        );
        $controller->setContainer($container);

        return $controller;
    }

    private function runFixture(StagingSyncStatusEnum $status): StagingSyncRun
    {
        return (new StagingSyncRun())
            ->setStatus($status)
            ->setTriggeredBy('admin@example.test')
            ->setStartedAt(new \DateTimeImmutable('2026-08-04T10:00:00+00:00'));
    }

    // ---- guard de producción, en las 3 rutas ----

    public function testStatusReturns404WhenNotProduction(): void
    {
        $response = $this->controller('staging')->status();

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testTriggerReturns404WhenNotProduction(): void
    {
        $this->service->expects($this->never())->method('trigger');

        $response = $this->controller('staging')->trigger();

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSyncStreamReturns404WhenNotProduction(): void
    {
        $response = $this->controller('staging')->syncStream();

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testStatusReturns404WhenDeploymentStageIsEmpty(): void
    {
        // Valor por defecto de #[Autowire('%env(default::DEPLOYMENT_STAGE)%')]
        // cuando la variable no está definida (dev local, CI) — nunca debe
        // colar como "production".
        $response = $this->controller('')->status();

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    // ---- status ----

    public function testStatusReturnsLatestAndRecentInProduction(): void
    {
        $latest = $this->runFixture(StagingSyncStatusEnum::SUCCESS);
        $this->service->method('latest')->willReturn($latest);
        $this->service->method('recent')->willReturn([$latest]);

        $response = $this->controller('production')->status();
        $data = json_decode($response->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('SUCCESS', $data['latest']['status']);
        $this->assertCount(1, $data['recent']);
    }

    public function testStatusReturnsNullLatestWhenNeverRun(): void
    {
        $this->service->method('latest')->willReturn(null);
        $this->service->method('recent')->willReturn([]);

        $response = $this->controller('production')->status();
        $data = json_decode($response->getContent(), true);

        $this->assertNull($data['latest']);
        $this->assertSame([], $data['recent']);
    }

    // ---- trigger ----

    public function testTriggerDelegatesToServiceInProduction(): void
    {
        $this->service->expects($this->once())->method('trigger');
        $this->service->method('latest')->willReturn($this->runFixture(StagingSyncStatusEnum::RUNNING));

        $response = $this->controller('production')->trigger();
        $data = json_decode($response->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('RUNNING', $data['latest']['status']);
    }

    public function testTriggerReturnsTheExceptionCodeWhenAlreadyRunning(): void
    {
        $this->service->method('trigger')
            ->willThrowException(new MyCurrentException('STAGING_SYNC_ALREADY_RUNNING', 'Ya hay una sincronización en curso.', 409));

        $response = $this->controller('production')->trigger();

        $this->assertSame(409, $response->getStatusCode());
    }
}
