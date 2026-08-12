<?php

namespace App\Tests\Controller;

use App\Controller\DashboardCatalogController;
use App\DTO\SyncProductsDto;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderCatalogInterface;
use App\Provider\ProviderRegistry;
use App\Service\CommunicationProductService;
use App\Service\Etecsa\SyncResult;
use App\Service\Provider\CommunicationCatalogSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \App\Controller\DashboardCatalogController::syncProducts
 *
 * Bug real corregido (2026-08-10): este endpoint delegaba en
 * TakeProductService::takeProduct(), que solo sincronizaba ETECSA
 * hardcodeado (y filtraba entornos por scope='ET', un artefacto legacy que
 * habría excluido cualquier Environment de DTOne/CSQ). El botón
 * "Sincronizar productos" del dashboard nunca sincronizaba CSQ/DTOne, solo
 * el comando de consola app:provider:sync-products lo hacía. Ahora itera
 * ProviderRegistry::allImplementing(ProviderCatalogInterface::class).
 */
class DashboardCatalogControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationCatalogSyncService&MockObject $catalogSyncService;
    private DashboardCatalogController $controller;

    private function buildController(array $providers): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->catalogSyncService = $this->createMock(CommunicationCatalogSyncService::class);

        // ProviderRegistry es `final` — se instancia real con proveedores
        // stub, en vez de mockearla (mismo patrón que el resto de tests que
        // la usan, ver ProviderRegistryTest).
        $this->controller = new DashboardCatalogController(
            $this->em,
            $this->createMock(NormalizerInterface::class),
            new ProviderRegistry($providers),
            $this->catalogSyncService,
            $this->createMock(CommunicationProductService::class),
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    private function providerStub(CommunicationProviderEnum $code): ProviderCatalogInterface&MockObject
    {
        $provider = $this->createMock(ProviderCatalogInterface::class);
        $provider->method('getCode')->willReturn($code);

        return $provider;
    }

    public function testSyncsEveryRegisteredCatalogProviderNotJustEtecsa(): void
    {
        $etecsa = $this->providerStub(CommunicationProviderEnum::ETECSA);
        $csq = $this->providerStub(CommunicationProviderEnum::CSQ);
        $this->buildController([$etecsa, $csq]);

        $environment = $this->createMock(Environment::class);
        $environmentRepo = $this->createMock(EntityRepository::class);
        $environmentRepo->method('findBy')->with(['type' => 'TEST', 'isActive' => true])->willReturn([$environment]);
        $this->em->method('getRepository')->with(Environment::class)->willReturn($environmentRepo);

        $this->catalogSyncService->expects($this->exactly(2))
            ->method('syncProducts')
            ->willReturnMap([
                [CommunicationProviderEnum::ETECSA, $environment, new SyncResult(5, 1, 0)],
                [CommunicationProviderEnum::CSQ, $environment, new SyncResult(86, 0, 0)],
            ]);

        $response = $this->controller->syncProducts(new SyncProductsDto('TEST'));
        $body = json_decode($response->getContent(), true);

        $this->assertTrue($body['synced']);
        $this->assertSame(91, $body['items']);
        $this->assertCount(2, $body['providers']);
        $this->assertSame('ETECSA', $body['providers'][0]['provider']);
        $this->assertSame(5, $body['providers'][0]['created']);
        $this->assertSame('CSQ', $body['providers'][1]['provider']);
        $this->assertSame(86, $body['providers'][1]['created']);
    }

    public function testOneProviderFailingDoesNotAbortTheOthers(): void
    {
        $csq = $this->providerStub(CommunicationProviderEnum::CSQ);
        $dtone = $this->providerStub(CommunicationProviderEnum::DTONE);
        $this->buildController([$csq, $dtone]);

        $environment = $this->createMock(Environment::class);
        $environmentRepo = $this->createMock(EntityRepository::class);
        $environmentRepo->method('findBy')->willReturn([$environment]);
        $this->em->method('getRepository')->willReturn($environmentRepo);

        $this->catalogSyncService->method('syncProducts')->willReturnCallback(
            function (CommunicationProviderEnum $code) {
                if ($code === CommunicationProviderEnum::CSQ) {
                    throw new \RuntimeException('Faltan credenciales de CSQ');
                }

                return new SyncResult(3, 0, 0);
            }
        );

        $response = $this->controller->syncProducts(new SyncProductsDto('TEST'));
        $body = json_decode($response->getContent(), true);

        $this->assertTrue($body['synced']);
        $this->assertSame('Faltan credenciales de CSQ', $body['providers'][0]['error']);
        $this->assertSame(0, $body['providers'][0]['created']);
        $this->assertNull($body['providers'][1]['error']);
        $this->assertSame(3, $body['providers'][1]['created']);
        // El total solo cuenta lo que sí se sincronizó.
        $this->assertSame(3, $body['items']);
    }
}
