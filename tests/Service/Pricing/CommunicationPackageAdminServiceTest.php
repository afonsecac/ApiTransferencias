<?php

namespace App\Tests\Service\Pricing;

use App\DTO\CreateCommunicationPackageBatchDto;
use App\DTO\CreateCommunicationPackageDto;
use App\DTO\UpdateCommunicationPackageDto;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationProductRepository;
use App\Service\Pricing\CommunicationPackageAdminService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\CommunicationPackageAdminService
 */
class CommunicationPackageAdminServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationProductRepository&MockObject $productRepository;
    private CommunicationPackageAdminService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->productRepository = $this->createMock(CommunicationProductRepository::class);

        $this->service = new CommunicationPackageAdminService($this->em, $this->productRepository);
    }

    private function repoReturning(mixed $value): EntityRepository&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($value);

        return $repo;
    }

    /**
     * $contracts es el lado inverso (mappedBy) de CommunicationContract::
     * $packages — no tiene un addContract() público porque nunca se muta
     * desde este lado en producción; en el test se puebla por reflexión
     * para simular "este paquete ya tiene un contrato asociado".
     */
    private function addContractTo(CommunicationPackage $package, CommunicationContract $contract): void
    {
        $property = new \ReflectionProperty($package, 'contracts');
        $property->setAccessible(true);
        $property->getValue($package)->add($contract);
    }

    public function testCreatePersistsAllFields(): void
    {
        $dto = new CreateCommunicationPackageDto(
            name: 'Recarga 500 CUP',
            description: 'Recarga 500 CUP',
            knowMore: 'Detalle',
            destinationAmount: 500.0,
            destinationCurrency: 'cup',
            isActive: true,
            displayOrder: 3,
            benefits: [['type' => 'CREDITS']],
            tags: ['AIRTIME'],
            service: ['name' => 'Mobile'],
            validity: ['quantity' => 30, 'unit' => 'DAYS'],
        );

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(CommunicationPackage::class));
        $this->em->expects($this->once())->method('flush');

        $package = $this->service->create($dto);

        $this->assertSame('Recarga 500 CUP', $package->getName());
        $this->assertSame(500.0, $package->getDestinationAmount());
        // destinationCurrency siempre se normaliza a mayúsculas.
        $this->assertSame('CUP', $package->getDestinationCurrency());
        $this->assertSame(3, $package->getDisplayOrder());
        $this->assertSame(['AIRTIME'], $package->getTags());
    }

    /**
     * active_start_at es una columna `timestamp without time zone`: si se
     * persistiera el DateTimeImmutable con el offset original (ej.
     * "-04:00" de Cuba) en vez de normalizarlo a UTC, Doctrine relee esos
     * mismos dígitos asumiendo UTC y la hora mostrada en el frontend se
     * desplaza. Verifica que el string de entrada con offset -04:00 se
     * traduce a su instante UTC equivalente antes de guardarse.
     */
    public function testCreateNormalizesActiveStartAtOffsetToUtcBeforePersisting(): void
    {
        $dto = new CreateCommunicationPackageDto(
            name: 'Recarga 500 CUP',
            description: 'Recarga 500 CUP',
            destinationAmount: 500.0,
            destinationCurrency: 'CUP',
            activeStartAt: '2026-08-12T20:00:00-04:00',
        );

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $package = $this->service->create($dto);

        $this->assertSame('UTC', $package->getActiveStartAt()?->getTimezone()->getName());
        $this->assertSame('2026-08-13T00:00:00+00:00', $package->getActiveStartAt()?->format('c'));
    }

    public function testCreateBatchGeneratesOnePackagePerAmountInRange(): void
    {
        $dto = new CreateCommunicationPackageBatchDto(
            nameTemplate: 'Cubacel {monto} CUP',
            descriptionTemplate: 'Recarga de {monto} CUP',
            fromAmount: 100.0,
            toAmount: 300.0,
            step: 100.0,
            destinationCurrency: 'cup',
        );

        $this->em->expects($this->exactly(3))->method('persist')->with($this->isInstanceOf(CommunicationPackage::class));
        $this->em->expects($this->once())->method('flush');

        $packages = $this->service->createBatch($dto);

        $this->assertCount(3, $packages);
        $this->assertSame(['Cubacel 100 CUP', 'Cubacel 200 CUP', 'Cubacel 300 CUP'], array_map(fn (CommunicationPackage $p) => $p->getName(), $packages));
        $this->assertSame(['Recarga de 100 CUP', 'Recarga de 200 CUP', 'Recarga de 300 CUP'], array_map(fn (CommunicationPackage $p) => $p->getDescription(), $packages));
        $this->assertSame([100.0, 200.0, 300.0], array_map(fn (CommunicationPackage $p) => $p->getDestinationAmount(), $packages));
        $this->assertSame('CUP', $packages[0]->getDestinationCurrency());
    }

    public function testCreateBatchWithSingleAmountWhenFromEqualsToGeneratesOnePackage(): void
    {
        $dto = new CreateCommunicationPackageBatchDto(
            nameTemplate: 'Recarga {monto}',
            descriptionTemplate: 'Recarga {monto}',
            fromAmount: 50.0,
            toAmount: 50.0,
            step: 10.0,
            destinationCurrency: 'CUP',
        );

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $packages = $this->service->createBatch($dto);

        $this->assertCount(1, $packages);
        $this->assertSame(50.0, $packages[0]->getDestinationAmount());
    }

    public function testCreateBatchThrowsWhenRangeExceedsMaxBatchSize(): void
    {
        $dto = new CreateCommunicationPackageBatchDto(
            nameTemplate: 'Recarga {monto}',
            descriptionTemplate: 'Recarga {monto}',
            fromAmount: 1.0,
            toAmount: 1000.0,
            step: 1.0,
            destinationCurrency: 'CUP',
        );

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('máximo permitido');

        $this->service->createBatch($dto);
    }

    public function testUpdateOnlyTouchesFieldsProvided(): void
    {
        $package = (new CommunicationPackage())
            ->setName('Original')
            ->setDescription('Original')
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setDisplayOrder(1);

        $dto = new UpdateCommunicationPackageDto(name: 'Renombrado');

        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->update($package, $dto);

        $this->assertSame('Renombrado', $updated->getName());
        // No tocado por el DTO parcial:
        $this->assertSame(500.0, $updated->getDestinationAmount());
        $this->assertSame(1, $updated->getDisplayOrder());
    }

    public function testToggleFlipsIsActiveAndFlushes(): void
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(1.0)->setDestinationCurrency('USD');
        $this->assertTrue($package->isActive());

        $this->em->expects($this->once())->method('flush');

        $this->service->toggle($package);

        $this->assertFalse($package->isActive());
    }

    public function testDeleteThrowsWhenPackageHasContracts(): void
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(1.0)->setDestinationCurrency('USD');
        $existingContract = $this->createMock(CommunicationContract::class);
        $this->addContractTo($package, $existingContract);

        $this->em->expects($this->never())->method('remove');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('tiene contratos asociados');

        $this->service->delete($package);
    }

    public function testDeleteRemovesPackageWhenNoContractsExist(): void
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(1.0)->setDestinationCurrency('USD');

        $this->em->expects($this->once())->method('remove')->with($package);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($package);
    }

    public function testCoverageMapsMatchingProductsToPlainArrays(): void
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP');

        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getProvider')->willReturn('CSQ');
        $product->method('getId')->willReturn(7);
        $product->method('getExternalRef')->willReturn('7854-250');
        $product->method('getDescription')->willReturn('Cubacel');
        $product->method('getPrice')->willReturn(11.36);
        $product->method('getPriceCurrency')->willReturn('USD');

        $environment = $this->createMock(Environment::class);
        $this->productRepository->expects($this->once())
            ->method('findMatchingDestination')
            ->with(500.0, 'CUP', $environment)
            ->willReturn([$product]);

        $coverage = $this->service->coverage($package, $environment);

        $this->assertSame([[
            'provider' => 'CSQ',
            'productId' => 7,
            'externalRef' => '7854-250',
            'description' => 'Cubacel',
            'wholesalePrice' => 11.36,
            'priceCurrency' => 'USD',
        ]], $coverage);
    }
}
