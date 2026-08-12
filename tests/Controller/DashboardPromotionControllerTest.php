<?php

namespace App\Tests\Controller;

use App\Controller\DashboardPromotionController;
use App\DTO\SetPromotionProviderProductDto;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotionProviderProduct;
use App\Entity\CommunicationPromotions;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPromotionsRepository;
use App\Service\CommunicationPromotionService;
use App\Service\Pricing\CommunicationPromotionBindingService;
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
 * proveedor — el resto del controlador (CRUD de promociones) no cambió.
 */
class DashboardPromotionControllerTest extends TestCase
{
    private CommunicationPromotionsRepository&MockObject $repository;
    private CommunicationPromotionBindingService&MockObject $bindingService;
    private DashboardPromotionController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CommunicationPromotionsRepository::class);
        $this->bindingService = $this->createMock(CommunicationPromotionBindingService::class);

        $this->controller = new DashboardPromotionController(
            $this->repository,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(NormalizerInterface::class),
            $this->createMock(CommunicationPromotionService::class),
            $this->bindingService,
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
}
