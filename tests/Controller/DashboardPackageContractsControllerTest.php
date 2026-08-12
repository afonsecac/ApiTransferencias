<?php

namespace App\Tests\Controller;

use App\Controller\DashboardPackageContractsController;
use App\DTO\CreateFixedContractDto;
use App\DTO\CreateRateContractDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Exception\MyCurrentException;
use App\Service\CommunicationPackageService;
use App\Service\Pricing\ContractBatchResult;
use App\Service\Pricing\PackageContractService;
use App\Service\Pricing\PackageSalePriceResolver;
use App\Service\Pricing\PriceSourceEnum;
use App\Service\Pricing\ResolvedSalePrice;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\DashboardPackageContractsController
 */
class DashboardPackageContractsControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private PackageContractService&MockObject $contractService;
    private CommunicationPackageService&MockObject $packageService;
    private PackageSalePriceResolver&MockObject $salePriceResolver;
    private DashboardPackageContractsController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->contractService = $this->createMock(PackageContractService::class);
        $this->packageService = $this->createMock(CommunicationPackageService::class);
        $this->salePriceResolver = $this->createMock(PackageSalePriceResolver::class);

        $this->controller = new DashboardPackageContractsController(
            $this->em,
            $this->contractService,
            $this->packageService,
            $this->salePriceResolver,
        );

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

    public function testShowReturnsNotFoundWhenContractDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPricePackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->show(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testShowReturnsNotFoundWhenRowExistsButIsNotAContract(): void
    {
        $notAContract = (new CommunicationPricePackage())->setIsContract(false);

        $this->em->method('getRepository')->with(CommunicationPricePackage::class)
            ->willReturn($this->repoReturning($notAContract));

        $response = $this->controller->show(1);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreateFixedReturnsCreatedWithSerializedContract(): void
    {
        $contract = (new CommunicationPricePackage())
            ->setContractMode('FIXED')
            ->setAmount(10.0)
            ->setCurrency('USD');

        $this->contractService->method('createFixed')->willReturn($contract);

        $dto = new CreateFixedContractDto(tenantId: 1, referencePackageId: 1, amount: 10.0, currency: 'USD');
        $response = $this->controller->createFixed($dto);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(10.0, $data['amount']);
        $this->assertSame('FIXED', $data['contractMode']);
    }

    public function testCreateFixedMapsDomainExceptionToItsHttpCode(): void
    {
        $this->contractService->method('createFixed')
            ->willThrowException(new MyCurrentException('TENANT_NOT_FOUND', 'Tenant not found', 404));

        $dto = new CreateFixedContractDto(tenantId: 999, referencePackageId: 1, amount: 10.0, currency: 'USD');
        $response = $this->controller->createFixed($dto);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Tenant not found', $data['error']['message']);
    }

    public function testCreateByRateReturnsBatchResult(): void
    {
        $this->contractService->method('createByRate')
            ->willReturn(new ContractBatchResult(3, 1, [10, 11, 12]));

        $dto = new CreateRateContractDto(referencePackageId: 1, basePrice: 100.0, baseCurrency: 'USD', rateValue: 1.1, currency: 'USD', environmentId: 5);
        $response = $this->controller->createByRate($dto);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(3, $data['created']);
        $this->assertSame(1, $data['updated']);
        $this->assertSame([10, 11, 12], $data['accountIds']);
    }

    public function testToggleReturnsNotFoundWhenContractDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPricePackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->toggle(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteReturnsNotFoundWhenContractDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationPricePackage::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->delete(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteDelegatesToPackageServiceWhenContractExists(): void
    {
        $contract = (new CommunicationPricePackage())->setIsContract(true);

        $this->em->method('getRepository')->with(CommunicationPricePackage::class)
            ->willReturn($this->repoReturning($contract));

        $this->packageService->expects($this->once())->method('deletePrice')->with($contract);

        $response = $this->controller->delete(1);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPreviewReturnsNotFoundWhenPackageDoesNotExist(): void
    {
        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $this->repoReturning(null)],
        ]);

        $request = new \Symfony\Component\HttpFoundation\Request(['packageId' => 999, 'tenantId' => 1]);
        $response = $this->controller->preview($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testPreviewReturnsResolvedPrice(): void
    {
        $package = $this->createMock(CommunicationClientPackage::class);
        $tenant = $this->createMock(Account::class);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $this->repoReturning($package)],
            [Account::class, $this->repoReturning($tenant)],
        ]);

        $this->salePriceResolver->method('resolve')
            ->with($package, $tenant)
            ->willReturn(new ResolvedSalePrice(15.0, 'USD', PriceSourceEnum::CONTRACT, 42));

        $request = new \Symfony\Component\HttpFoundation\Request(['packageId' => 1, 'tenantId' => 2]);
        $response = $this->controller->preview($request);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(15.0, $data['amount']);
        $this->assertSame('CONTRACT', $data['source']);
        $this->assertSame(42, $data['contractId']);
    }
}
