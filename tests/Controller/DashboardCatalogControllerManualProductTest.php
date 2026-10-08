<?php

namespace App\Tests\Controller;

use App\Controller\DashboardCatalogController;
use App\DTO\CreateManualProductDto;
use App\Entity\CommunicationProduct;
use App\Exception\MyCurrentException;
use App\Provider\ProviderRegistry;
use App\Service\CommunicationProductService;
use App\Service\Provider\CommunicationCatalogSyncService;
use App\Service\Provider\Manual\CsqManualProductBuilder;
use App\Service\Provider\Manual\EtecsaManualProductBuilder;
use App\Service\Provider\Manual\ManualProductBuilderRegistry;
use App\Service\Provider\Manual\ManualProductResult;
use App\Service\Provider\Manual\ManualProductService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @covers \App\Controller\DashboardCatalogController::manualProductSchema
 * @covers \App\Controller\DashboardCatalogController::createManualProduct
 */
class DashboardCatalogControllerManualProductTest extends TestCase
{
    private ManualProductService&MockObject $manualProductService;
    private DashboardCatalogController $controller;

    protected function setUp(): void
    {
        $this->manualProductService = $this->createMock(ManualProductService::class);

        $this->controller = new DashboardCatalogController(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(NormalizerInterface::class),
            new ProviderRegistry([]),
            $this->createMock(CommunicationCatalogSyncService::class),
            $this->createMock(CommunicationProductService::class),
            new ManualProductBuilderRegistry([new CsqManualProductBuilder(), new EtecsaManualProductBuilder()]),
            $this->manualProductService,
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    public function testSchemaReturnsFieldsForKnownProvider(): void
    {
        $request = new Request(['provider' => 'CSQ']);

        $response = $this->controller->manualProductSchema($request);
        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('CSQ', $payload['provider']);
        $this->assertContains('articleId', array_column($payload['fields'], 'key'));
    }

    public function testSchemaReturnsNotFoundForUnknownProviderCode(): void
    {
        $request = new Request(['provider' => 'FOO']);

        $response = $this->controller->manualProductSchema($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSchemaReturns422ForProviderWithoutBuilder(): void
    {
        $request = new Request(['provider' => 'DTONE']);

        $response = $this->controller->manualProductSchema($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testCreateManualProductReturns201WithCreatedProducts(): void
    {
        $product = (new CommunicationProduct())->setEnvironment(null)->setEnabled(true)->setPrice(26.4);
        $this->manualProductService->method('create')->willReturn(new ManualProductResult(1, 0, 0, [$product]));

        $response = $this->controller->createManualProduct(new CreateManualProductDto('CSQ', 1, ['articleId' => '8142']));
        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame(1, $payload['created']);
        $this->assertCount(1, $payload['products']);
    }

    public function testCreateManualProductTranslatesDomainExceptionToJson(): void
    {
        $this->manualProductService->method('create')
            ->willThrowException(new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404));

        $response = $this->controller->createManualProduct(new CreateManualProductDto('CSQ', 999, []));
        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('ENVIRONMENT_NOT_FOUND', $payload['error']['code']);
    }
}
