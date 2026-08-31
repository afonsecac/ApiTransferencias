<?php

namespace App\Tests\Controller;

use App\Controller\DashboardCommunicationPackagesController;
use App\DTO\CreateCommunicationPackageBatchDto;
use App\DTO\CreateCommunicationPackageDto;
use App\DTO\SetPackageProviderProductDto;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\CommunicationPackageAdminService;
use App\Service\Pricing\CommunicationPackageBindingService;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\DashboardCommunicationPackagesController
 */
class DashboardCommunicationPackagesControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationPackageAdminService&MockObject $packageService;
    private PackageCatalogResolver&MockObject $catalogResolver;
    private CommunicationPackageBindingService&MockObject $bindingService;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private DashboardCommunicationPackagesController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->packageService = $this->createMock(CommunicationPackageAdminService::class);
        $this->catalogResolver = $this->createMock(PackageCatalogResolver::class);
        $this->bindingService = $this->createMock(CommunicationPackageBindingService::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->bindingRepo->method('findPackageIdsWithBindings')->willReturn([]);
        $this->bindingRepo->method('findAllForPackage')->willReturn([]);

        $this->controller = new DashboardCommunicationPackagesController($this->em, $this->packageService, $this->catalogResolver, $this->bindingService, $this->bindingRepo);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    private function repoReturning(mixed $value): EntityRepository&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($value);

        return $repo;
    }

    private function package(): CommunicationPackage
    {
        return (new CommunicationPackage())
            ->setName('Recarga 500 CUP')
            ->setDescription('Recarga 500 CUP')
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');
    }

    public function testShowReturnsNotFoundWhenPackageDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->show(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testShowReturnsSerializedPackage(): void
    {
        $package = $this->package();

        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));

        $response = $this->controller->show(1);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Recarga 500 CUP', $data['name']);
        $this->assertEquals(['amount' => 500.0, 'unit' => 'CUP', 'unit_type' => 'CURRENCY'], $data['destination']);
        $this->assertFalse($data['hasBindings']);
    }

    public function testShowMarksHasBindingsTrueWhenPackageHasAtLeastOneBinding(): void
    {
        $package = $this->package();
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->bindingRepo->method('findAllForPackage')->willReturn([$this->createMock(\App\Entity\CommunicationPackageProviderProduct::class)]);
        $this->controller = new DashboardCommunicationPackagesController($this->em, $this->packageService, $this->catalogResolver, $this->bindingService, $this->bindingRepo);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);

        $response = $this->controller->show(1);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['hasBindings']);
    }

    public function testCreateReturnsCreatedWithSerializedPackage(): void
    {
        $package = $this->package();
        $this->packageService->method('create')->willReturn($package);

        $dto = new CreateCommunicationPackageDto(name: 'X', description: 'X', destinationAmount: 500.0, destinationCurrency: 'CUP');
        $response = $this->controller->create($dto);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateBatchReturnsCreatedCountAndPackageIds(): void
    {
        $p1 = $this->package();
        $p2 = $this->package();
        // El id real solo existe tras persist/flush contra la BD; en el
        // test unitario con EM mockeado nunca se asigna (permanece null en
        // ambos), así que solo verificamos que el conteo sea correcto.
        $this->packageService->method('createBatch')->willReturn([$p1, $p2]);

        $dto = new CreateCommunicationPackageBatchDto(
            nameTemplate: 'Cubacel {monto} CUP',
            descriptionTemplate: 'Recarga {monto} CUP',
            fromAmount: 100.0,
            toAmount: 200.0,
            step: 100.0,
            destinationCurrency: 'CUP',
        );
        $response = $this->controller->createBatch($dto);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['created']);
        $this->assertCount(2, $data['packageIds']);
    }

    public function testCreateBatchMapsDomainExceptionToItsHttpCode(): void
    {
        $this->packageService->method('createBatch')
            ->willThrowException(new MyCurrentException('PACKAGE_BATCH_TOO_LARGE', 'El rango genera demasiados paquetes', 422));

        $dto = new CreateCommunicationPackageBatchDto(
            nameTemplate: 'Cubacel {monto} CUP',
            descriptionTemplate: 'Recarga {monto} CUP',
            fromAmount: 1.0,
            toAmount: 1000.0,
            step: 1.0,
            destinationCurrency: 'CUP',
        );
        $response = $this->controller->createBatch($dto);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testDeleteMapsDomainExceptionToItsHttpCode(): void
    {
        $package = $this->package();
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));
        $this->packageService->method('delete')
            ->willThrowException(new MyCurrentException('COMMUNICATION_PACKAGE_HAS_CONTRACTS', 'No se puede eliminar: tiene contratos asociados', 409));

        $response = $this->controller->delete(1);

        $this->assertSame(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No se puede eliminar: tiene contratos asociados', $data['error']['message']);
    }

    public function testPreviewReturnsNotVisibleWhenResolverReturnsNull(): void
    {
        $package = $this->package();
        $tenant = $this->createMock(Account::class);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [Account::class, $this->repoReturning($tenant)],
        ]);
        $this->catalogResolver->method('offerFor')->willReturn(null);

        $request = new Request(['packageId' => 1, 'tenantId' => 2]);
        $response = $this->controller->preview($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('NOT_VISIBLE', $data['source']);
    }

    public function testPreviewReturnsResolvedOffer(): void
    {
        $package = $this->package();
        $tenant = $this->createMock(Account::class);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [Account::class, $this->repoReturning($tenant)],
        ]);
        $offer = new ResolvedPackageOffer($package, 12.5, 'USD', PackageOfferSourceEnum::TENANT_CONTRACT, contractId: 7);
        $this->catalogResolver->method('offerFor')->willReturn($offer);

        $request = new Request(['packageId' => 1, 'tenantId' => 2]);
        $response = $this->controller->preview($request);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(12.5, $data['amount']);
        $this->assertSame('TENANT_CONTRACT', $data['source']);
        $this->assertSame(7, $data['contractId']);
    }

    public function testCoverageReturnsNotFoundWhenPackageDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->coverage(999, new Request());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCoverageReturnsServiceResult(): void
    {
        $package = $this->package();
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));
        $this->packageService->method('coverage')->willReturn([[
            'provider' => 'CSQ',
            'productId' => 1,
            'externalRef' => 'x',
            'description' => null,
            'wholesalePrice' => 10.0,
            'priceCurrency' => 'USD',
        ]]);

        $response = $this->controller->coverage(1, new Request());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('CSQ', $data[0]['provider']);
    }

    private function product(): CommunicationProduct
    {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getProvider')->willReturn('CSQ');
        $product->method('getId')->willReturn(1);
        $product->method('getExternalRef')->willReturn('x');
        $product->method('getPrice')->willReturn(10.0);
        $product->method('getPriceCurrency')->willReturn('USD');
        $product->method('getRequiredIdentifierFields')->willReturn([['accountIdentifier']]);

        return $product;
    }

    public function testListBindingsReturnsNotFoundWhenPackageDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->listBindings(999, new Request());

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testListBindingsReturnsServiceResult(): void
    {
        $package = $this->package();
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));
        $this->bindingService->method('listBindings')->willReturn([
            ['provider' => 'ETECSA', 'boundProduct' => $this->product(), 'candidates' => [], 'autoMatched' => true],
            ['provider' => 'CSQ', 'boundProduct' => null, 'candidates' => [$this->product()], 'autoMatched' => false],
        ]);

        $response = $this->controller->listBindings(1, new Request());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertSame('ETECSA', $data[0]['provider']);
        $this->assertSame('CSQ', $data[0]['boundProduct']['provider']);
        $this->assertSame('CSQ', $data[1]['provider']);
        $this->assertNull($data[1]['boundProduct']);
        $this->assertCount(1, $data[1]['candidates']);
        $this->assertTrue($data[0]['autoMatched']);
        $this->assertFalse($data[1]['autoMatched']);
        // requiredIdentifierFields (ver App\Entity\CommunicationProduct) viaja
        // en la serialización — el admin necesita verlo al elegir el vínculo
        // paquete->producto (ej. Nauta WIFI exige accountIdentifier, no un
        // número de teléfono).
        $this->assertSame([['accountIdentifier']], $data[0]['boundProduct']['requiredIdentifierFields']);
        $this->assertSame([['accountIdentifier']], $data[1]['candidates'][0]['requiredIdentifierFields']);
    }

    public function testSetBindingReturnsNotFoundWhenPackageDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->setBinding(999, 'CSQ', new SetPackageProviderProductDto(productId: 1));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSetBindingReturnsTheUpdatedBinding(): void
    {
        $package = $this->package();
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));

        $binding = $this->createMock(\App\Entity\CommunicationPackageProviderProduct::class);
        $binding->method('getProvider')->willReturn('CSQ');
        $binding->method('getProduct')->willReturn($this->product());
        $this->bindingService->expects($this->once())->method('setBinding')->with($package, 'CSQ', 1)->willReturn($binding);

        $response = $this->controller->setBinding(1, 'CSQ', new SetPackageProviderProductDto(productId: 1));

        $data = json_decode($response->getContent(), true);
        $this->assertSame('CSQ', $data['provider']);
        $this->assertSame(1, $data['boundProduct']['productId']);
    }

    public function testSetBindingMapsDomainExceptionToItsHttpCode(): void
    {
        $package = $this->package();
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));
        $this->bindingService->method('setBinding')
            ->willThrowException(new MyCurrentException('COMMUNICATION_PRODUCT_NOT_FOUND', 'Communication product not found', 404));

        $response = $this->controller->setBinding(1, 'CSQ', new SetPackageProviderProductDto(productId: 999));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteBindingReturnsNotFoundWhenPackageDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->deleteBinding(999, 'CSQ');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteBindingDelegatesToServiceAndReturnsDeleted(): void
    {
        $package = $this->package();
        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));
        $this->bindingService->expects($this->once())->method('removeBinding')->with($package, 'CSQ');

        $response = $this->controller->deleteBinding(1, 'CSQ');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['deleted']);
    }
}
