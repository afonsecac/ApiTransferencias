<?php

namespace App\Tests\Controller;

use App\Controller\DashboardCommunicationContractsController;
use App\DTO\CreateCommunicationContractBatchDto;
use App\DTO\CreateCommunicationContractDto;
use App\DTO\CreateCommunicationContractRangeDto;
use App\DTO\UpdateCommunicationContractDto;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Exception\MyCurrentException;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\ContractBatchResult;
use App\Service\Pricing\ContractRangeResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Controller\DashboardCommunicationContractsController
 */
class DashboardCommunicationContractsControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationContractService&MockObject $contractService;
    private DashboardCommunicationContractsController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->contractService = $this->createMock(CommunicationContractService::class);

        $this->controller = new DashboardCommunicationContractsController($this->em, $this->contractService);

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

    private function contract(): CommunicationContract
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP');

        return (new CommunicationContract())
            ->setCommunicationPackage($package)
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');
    }

    public function testShowReturnsNotFoundWhenContractDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationContract::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->show(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testShowReturnsSerializedContract(): void
    {
        $contract = $this->contract();
        $this->em->method('getRepository')->with(CommunicationContract::class)
            ->willReturn($this->repoReturning($contract));

        $response = $this->controller->show(1);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(10.0, $data['price']);
        $this->assertSame('USD', $data['currency']);
        $this->assertTrue($data['isActive']);
    }

    public function testCreateReturnsCreatedWithSerializedContract(): void
    {
        $this->contractService->method('createSingle')->willReturn($this->contract());

        $dto = new CreateCommunicationContractDto(communicationPackageId: 1, price: 10.0, currency: 'USD');
        $response = $this->controller->create($dto);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testCreateMapsDomainExceptionToItsHttpCode(): void
    {
        $this->contractService->method('createSingle')
            ->willThrowException(new MyCurrentException('COMMUNICATION_PACKAGE_NOT_FOUND', 'Communication package not found', 404));

        $dto = new CreateCommunicationContractDto(communicationPackageId: 999, price: 10.0, currency: 'USD');
        $response = $this->controller->create($dto);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCreateBatchReturnsBatchResult(): void
    {
        $this->contractService->method('createBatch')
            ->willReturn(new ContractBatchResult(3, 1, [10, 11, 12]));

        $dto = new CreateCommunicationContractBatchDto(communicationPackageId: 1, environmentId: 5, price: 10.0, currency: 'USD');
        $response = $this->controller->createBatch($dto);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(3, $data['created']);
        $this->assertSame([10, 11, 12], $data['accountIds']);
    }

    public function testCreateByRangeReturnsRangeResult(): void
    {
        $this->contractService->method('createByRange')
            ->willReturn(new ContractRangeResult(2, 0, 1, [10, 11], [175.5]));

        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 100.0,
            toAmount: 200.0,
            step: 50.0,
            destinationCurrency: 'CUP',
            priceFrom: 5.0,
            priceTo: 10.0,
            currency: 'USD',
        );
        $response = $this->controller->createByRange($dto);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['created']);
        $this->assertSame(1, $data['skipped']);
        $this->assertSame([10, 11], $data['contractIds']);
        $this->assertSame([175.5], $data['skippedAmounts']);
    }

    public function testCreateByRangeMapsDomainExceptionToItsHttpCode(): void
    {
        $this->contractService->method('createByRange')
            ->willThrowException(new MyCurrentException('CONTRACT_RANGE_TOO_LARGE', 'El rango genera demasiados contratos', 422));

        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 1.0,
            toAmount: 1000.0,
            step: 1.0,
            destinationCurrency: 'CUP',
            priceFrom: 1.0,
            priceTo: 2.0,
            currency: 'USD',
        );
        $response = $this->controller->createByRange($dto);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testUpdateMapsDomainExceptionToItsHttpCode(): void
    {
        $contract = $this->contract();
        $this->em->method('getRepository')->with(CommunicationContract::class)
            ->willReturn($this->repoReturning($contract));
        $this->contractService->method('update')
            ->willThrowException(new MyCurrentException('COMMUNICATION_PACKAGE_NOT_FOUND', 'No existe ningún paquete con ese monto de destino', 404));

        $response = $this->controller->update(1, new UpdateCommunicationContractDto(destinationAmount: 999.0));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateReturnsNotFoundWhenContractDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationContract::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->update(999, new UpdateCommunicationContractDto(price: 5.0));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCloseDelegatesToServiceAndReturnsUpdatedContract(): void
    {
        $contract = $this->contract();
        $this->em->method('getRepository')->with(CommunicationContract::class)
            ->willReturn($this->repoReturning($contract));

        $this->contractService->expects($this->once())->method('close')->with($contract);

        $response = $this->controller->close(1);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteReturnsNotFoundWhenContractDoesNotExist(): void
    {
        $this->em->method('getRepository')->with(CommunicationContract::class)
            ->willReturn($this->repoReturning(null));

        $response = $this->controller->delete(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeleteDelegatesToServiceWhenContractExists(): void
    {
        $contract = $this->contract();
        $this->em->method('getRepository')->with(CommunicationContract::class)
            ->willReturn($this->repoReturning($contract));

        $this->contractService->expects($this->once())->method('delete')->with($contract);

        $response = $this->controller->delete(1);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteBulkWithoutTenantDeletesEverything(): void
    {
        $this->contractService->expects($this->once())->method('deleteAll')->with(null, null)->willReturn(41);

        $response = $this->controller->deleteBulk(new Request());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(41, $data['deleted']);
    }

    public function testDeleteBulkWithTenantPassesBothIdsToService(): void
    {
        $this->contractService->expects($this->once())->method('deleteAll')->with(5, 9)->willReturn(3);

        $response = $this->controller->deleteBulk(new Request(['tenantId' => '5', 'environmentId' => '9']));

        $data = json_decode($response->getContent(), true);
        $this->assertSame(3, $data['deleted']);
    }

    public function testDeleteBulkMapsDomainExceptionToItsHttpCode(): void
    {
        $this->contractService->method('deleteAll')
            ->willThrowException(new MyCurrentException('ENVIRONMENT_REQUIRED', 'environmentId is required when tenantId is given', 422));

        $response = $this->controller->deleteBulk(new Request(['tenantId' => '5']));

        $this->assertSame(422, $response->getStatusCode());
    }
}
