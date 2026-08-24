<?php

namespace App\Tests\Controller;

use App\Controller\DashboardPromotionController;
use App\DTO\CreatePromotionV2Dto;
use App\DTO\SetPromotionProviderProductDto;
use App\DTO\UpsertPromotionDto;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotionProviderProduct;
use App\Entity\CommunicationPromotions;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPromotionsRepository;
use App\Service\CommunicationPromotionService;
use App\Service\CreatePromotionV2Result;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\CommunicationPromotionBindingService;
use App\Service\Pricing\CommunicationPromotionEquivalenceService;
use App\Service\Pricing\PromotionEquivalenceResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \App\Controller\DashboardPromotionController
 *
 * Cubre solo los 3 endpoints nuevos de vínculo promoción→producto por
 * proveedor y el alta V2 — el resto del controlador (CRUD legacy) no
 * cambió.
 */
class DashboardPromotionControllerTest extends TestCase
{
    private CommunicationPromotionsRepository&MockObject $repository;
    private CommunicationPromotionBindingService&MockObject $bindingService;
    private CommunicationPromotionService&MockObject $promotionService;
    private CommunicationPromotionEquivalenceService&MockObject $equivalenceService;
    private CommunicationContractService&MockObject $contractService;
    private NormalizerInterface&MockObject $serializer;
    private DashboardPromotionController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CommunicationPromotionsRepository::class);
        $this->bindingService = $this->createMock(CommunicationPromotionBindingService::class);
        $this->promotionService = $this->createMock(CommunicationPromotionService::class);
        $this->equivalenceService = $this->createMock(CommunicationPromotionEquivalenceService::class);
        $this->contractService = $this->createMock(CommunicationContractService::class);
        $this->serializer = $this->createMock(NormalizerInterface::class);
        $this->serializer->method('normalize')->willReturn([]);

        $this->controller = new DashboardPromotionController(
            $this->repository,
            $this->createMock(EntityManagerInterface::class),
            $this->serializer,
            $this->promotionService,
            $this->bindingService,
            $this->equivalenceService,
            $this->contractService,
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    private function product(): CommunicationProduct
    {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getProvider')->willReturn('CSQ');
        $product->method('getId')->willReturn(1);
        $product->method('getExternalRef')->willReturn('ref-1');

        return $product;
    }

    public function testCreateRejectsWithGoneAndNeverTouchesTheService(): void
    {
        // Fase 2 de la deprecación de V1: el alta V1 queda cerrada — usar
        // createV2(). No debe persistir nada ni llamar al servicio.
        $this->promotionService->expects($this->never())->method('createPackagesForPromotion');

        $response = $this->controller->create(new UpsertPromotionDto());

        $this->assertSame(Response::HTTP_GONE, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('V1_PROMOTION_CREATION_DISABLED', $body['error']['code']);
    }

    public function testListBindingsReturnsNotFoundWhenPromotionDoesNotExist(): void
    {
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->listBindings(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testListBindingsReturnsServiceResult(): void
    {
        $promotion = new CommunicationPromotions();
        $this->repository->method('find')->willReturn($promotion);
        $this->bindingService->method('listBindings')->willReturn([
            ['provider' => 'ETECSA', 'boundProduct' => $this->product(), 'candidates' => []],
            ['provider' => 'CSQ', 'boundProduct' => null, 'candidates' => [$this->product()]],
        ]);

        $response = $this->controller->listBindings(1);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('ETECSA', $data[0]['provider']);
        $this->assertSame('CSQ', $data[0]['boundProduct']['provider']);
        $this->assertSame('CSQ', $data[1]['provider']);
        $this->assertNull($data[1]['boundProduct']);
        $this->assertCount(1, $data[1]['candidates']);
    }

    public function testSetBindingReturnsNotFoundWhenPromotionDoesNotExist(): void
    {
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->setBinding(999, 'CSQ', new SetPromotionProviderProductDto(productId: 1));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSetBindingReturnsTheUpdatedBinding(): void
    {
        $promotion = new CommunicationPromotions();
        $this->repository->method('find')->willReturn($promotion);

        $binding = $this->createMock(CommunicationPromotionProviderProduct::class);
        $binding->method('getProvider')->willReturn('CSQ');
        $binding->method('getProduct')->willReturn($this->product());
        $this->bindingService->expects($this->once())->method('setBinding')->with($promotion, 'CSQ', 1)->willReturn($binding);

        $response = $this->controller->setBinding(1, 'CSQ', new SetPromotionProviderProductDto(productId: 1));

        $data = json_decode($response->getContent(), true);
        $this->assertSame('CSQ', $data['provider']);
        $this->assertSame(1, $data['boundProduct']['productId']);
    }

    public function testSetBindingMapsDomainExceptionToItsHttpCode(): void
    {
        $promotion = new CommunicationPromotions();
        $this->repository->method('find')->willReturn($promotion);
        $this->bindingService->method('setBinding')
            ->willThrowException(new MyCurrentException('COMMUNICATION_PRODUCT_NOT_FOUND', 'Communication product not found', 404));

        $response = $this->controller->setBinding(1, 'CSQ', new SetPromotionProviderProductDto(productId: 999));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteBindingReturnsNotFoundWhenPromotionDoesNotExist(): void
    {
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->deleteBinding(999, 'CSQ');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteBindingDelegatesToServiceAndReturnsDeleted(): void
    {
        $promotion = new CommunicationPromotions();
        $this->repository->method('find')->willReturn($promotion);
        $this->bindingService->expects($this->once())->method('removeBinding')->with($promotion, 'CSQ');

        $response = $this->controller->deleteBinding(1, 'CSQ');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['deleted']);
    }

    private function v2Dto(): CreatePromotionV2Dto
    {
        return new CreatePromotionV2Dto(
            name: 'Promo V2',
            description: 'Promo V2',
            packageNameTemplate: 'Cubacel {monto} CUP',
            packageDescriptionTemplate: 'Cubacel {monto} CUP',
            startAt: '2026-08-18T00:00:00+00:00',
            endAt: '2026-08-25T23:59:00+00:00',
            environmentId: 4,
            destinationCurrency: 'CUP',
            amountFrom: 500.0,
            amountTo: 525.0,
            amountStep: 25.0,
        );
    }

    public function testCreateV2ReturnsThePromotionAndGeneratedPackages(): void
    {
        $promotion = new CommunicationPromotions();
        $packages = [
            (new CommunicationPackage())->setName('p1')->setDescription('p1')->setDestinationAmount(500.0)->setDestinationCurrency('CUP'),
            (new CommunicationPackage())->setName('p2')->setDescription('p2')->setDestinationAmount(525.0)->setDestinationCurrency('CUP'),
        ];
        $equivalences = new PromotionEquivalenceResult(
            [['provider' => 'DTONE', 'matched' => 2, 'error' => null]],
            [],
        );
        $result = new CreatePromotionV2Result($promotion, $packages, 3, $equivalences);
        $this->promotionService->expects($this->once())->method('createV2')->willReturn($result);

        $response = $this->controller->createV2($this->v2Dto());

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['packagesCreated']);
        $this->assertCount(2, $data['packages']);
        $this->assertEquals(500.0, $data['packages'][0]['destinationAmount']);
        $this->assertSame(3, $data['tenantContractsLinked']);
        $this->assertSame('DTONE', $data['equivalences']['providers'][0]['provider']);
        $this->assertSame([], $data['equivalences']['gaps']);
    }

    public function testCreateV2MapsDomainExceptionToItsHttpCode(): void
    {
        $this->promotionService->method('createV2')
            ->willThrowException(new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404));

        $response = $this->controller->createV2($this->v2Dto());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testEquivalencesReturnsNotFoundWhenPromotionDoesNotExist(): void
    {
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->equivalences(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testEquivalencesReturnsCoverageFromTheService(): void
    {
        $promotion = new CommunicationPromotions();
        $this->repository->method('find')->willReturn($promotion);
        $this->equivalenceService->expects($this->once())->method('coverage')->with($promotion)->willReturn(
            new PromotionEquivalenceResult([], [['packageId' => 1, 'destinationAmount' => 500.0, 'missingProviders' => ['DTONE']]]),
        );

        $response = $this->controller->equivalences(1);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('DTONE', $data['gaps'][0]['missingProviders'][0]);
    }

    public function testRefreshEquivalencesReturnsNotFoundWhenPromotionDoesNotExist(): void
    {
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->refreshEquivalences(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRefreshEquivalencesDelegatesToTheService(): void
    {
        $promotion = new CommunicationPromotions();
        $this->repository->method('find')->willReturn($promotion);
        $this->equivalenceService->expects($this->once())->method('refreshForPromotion')->with($promotion)->willReturn(
            new PromotionEquivalenceResult([['provider' => 'DTONE', 'matched' => 5, 'error' => null]], []),
        );

        $response = $this->controller->refreshEquivalences(1);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(5, $data['providers'][0]['matched']);
    }

    public function testLinkTenantContractsReturnsNotFoundWhenPromotionDoesNotExist(): void
    {
        $this->repository->method('find')->willReturn(null);

        $response = $this->controller->linkTenantContracts(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testLinkTenantContractsDelegatesToTheContractService(): void
    {
        $promotion = new CommunicationPromotions();
        $this->repository->method('find')->willReturn($promotion);
        $this->contractService->expects($this->once())->method('linkTenantContractsForPromotion')->with($promotion)->willReturn(4);

        $response = $this->controller->linkTenantContracts(1);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(4, $data['tenantContractsLinked']);
    }
}
