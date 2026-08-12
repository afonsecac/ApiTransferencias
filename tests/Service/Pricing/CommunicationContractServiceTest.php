<?php

namespace App\Tests\Service\Pricing;

use App\DTO\CreateCommunicationContractBatchDto;
use App\DTO\CreateCommunicationContractDto;
use App\DTO\CreateCommunicationContractRangeDto;
use App\DTO\UpdateCommunicationContractDto;
use App\Entity\Account;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Entity\Environment;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPackageRepository;
use App\Service\Pricing\CommunicationContractService;
use App\Service\Pricing\TargetAccountResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\Service\Pricing\CommunicationContractService
 */
class CommunicationContractServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TargetAccountResolver&MockObject $targetAccountResolver;
    private Security&MockObject $security;
    private CommunicationPackageRepository&MockObject $packageRepository;
    private CommunicationContractService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->targetAccountResolver = $this->createMock(TargetAccountResolver::class);
        $this->security = $this->createMock(Security::class);
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);

        $this->service = new CommunicationContractService($this->em, $this->targetAccountResolver, $this->security, $this->packageRepository);
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function repoReturning(mixed $value): EntityRepository&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($value);
        $repo->method('findOneBy')->willReturn($value);

        return $repo;
    }

    private function package(int $id): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName('P')->setDescription('P')
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP');
        $this->assignId($package, $id);

        return $package;
    }

    public function testCreateSingleThrowsWhenPackageNotFound(): void
    {
        $dto = new CreateCommunicationContractDto(communicationPackageId: 99, price: 10.0, currency: 'USD');

        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning(null));

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Communication package not found');

        $this->service->createSingle($dto);
    }

    public function testCreateSingleThrowsWhenEnvironmentIdMissingAndTenantGiven(): void
    {
        $dto = new CreateCommunicationContractDto(communicationPackageId: 1, tenantId: 99, price: 10.0, currency: 'USD');
        $package = $this->package(1);

        $this->em->method('getRepository')->with(CommunicationPackage::class)
            ->willReturn($this->repoReturning($package));

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('environmentId is required');

        $this->service->createSingle($dto);
    }

    public function testCreateSingleThrowsWhenTenantNotFound(): void
    {
        $dto = new CreateCommunicationContractDto(communicationPackageId: 1, tenantId: 99, environmentId: 5, price: 10.0, currency: 'USD');
        $package = $this->package(1);
        $environment = $this->createMock(Environment::class);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [Environment::class, $this->repoReturning($environment)],
        ]);
        $this->targetAccountResolver->method('resolveOne')->with($environment, 99)->willReturn(null);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Tenant not found');

        $this->service->createSingle($dto);
    }

    public function testCreateSingleWithoutTenantCreatesADefaultContract(): void
    {
        $dto = new CreateCommunicationContractDto(communicationPackageId: 1, price: 8.0, currency: 'USD');
        $package = $this->package(1);

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [CommunicationContract::class, $contractRepo],
        ]);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(CommunicationContract::class));
        $this->em->expects($this->once())->method('flush');

        $contract = $this->service->createSingle($dto);

        $this->assertNull($contract->getTenant());
        $this->assertSame($package, $contract->getCommunicationPackage());
        $this->assertSame(8.0, $contract->getPrice());
        $this->assertSame('USD', $contract->getCurrency());
        // Snapshot copiado del paquete, no pedido a mano.
        $this->assertSame(500.0, $contract->getDestinationAmount());
        $this->assertSame('CUP', $contract->getDestinationCurrency());
    }

    public function testCreateSingleReusesExistingOpenContractInsteadOfDuplicating(): void
    {
        $dto = new CreateCommunicationContractDto(communicationPackageId: 1, tenantId: 5, environmentId: 9, price: 20.0, currency: 'USD');
        $package = $this->package(1);
        $tenant = $this->createMock(Account::class);
        $environment = $this->createMock(Environment::class);

        $existing = (new CommunicationContract())
            ->setCommunicationPackage($package)
            ->setTenant($tenant)
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setPrice(10.0)
            ->setCurrency('USD');

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [Environment::class, $this->repoReturning($environment)],
            [CommunicationContract::class, $this->repoReturning($existing)],
        ]);
        $this->targetAccountResolver->method('resolveOne')->with($environment, 5)->willReturn($tenant);

        $this->em->expects($this->never())->method('persist');

        $contract = $this->service->createSingle($dto);

        $this->assertSame($existing, $contract);
        $this->assertSame(20.0, $contract->getPrice());
    }

    public function testCreateSingleStampsCreatedByFromSecurity(): void
    {
        $dto = new CreateCommunicationContractDto(communicationPackageId: 1, price: 8.0, currency: 'USD');
        $package = $this->package(1);
        $user = $this->createMock(User::class);

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [CommunicationContract::class, $contractRepo],
        ]);
        $this->security->method('getUser')->willReturn($user);

        $contract = $this->service->createSingle($dto);

        $this->assertSame($user, $contract->getCreatedBy());
    }

    public function testCreateBatchWithEmptyClientsAppliesToAllAccountsFromTargetResolver(): void
    {
        $dto = new CreateCommunicationContractBatchDto(
            communicationPackageId: 1,
            environmentId: 5,
            clients: [],
            price: 15.0,
            currency: 'USD',
        );
        $package = $this->package(1);
        $environment = $this->createMock(Environment::class);
        $account1 = $this->createMock(Account::class);
        $account1->method('getId')->willReturn(101);
        $account2 = $this->createMock(Account::class);
        $account2->method('getId')->willReturn(102);

        $this->targetAccountResolver->expects($this->once())
            ->method('resolve')
            ->with($environment, [])
            ->willReturn([$account1, $account2]);

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [Environment::class, $this->repoReturning($environment)],
            [CommunicationContract::class, $contractRepo],
        ]);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($e) use (&$persisted) { $persisted[] = $e; });
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createBatch($dto);

        $this->assertSame(2, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame([101, 102], $result->accountIds);
        $this->assertCount(2, $persisted);
    }

    public function testCreateBatchReturnsZeroWithoutFlushingWhenNoAccountsMatch(): void
    {
        $dto = new CreateCommunicationContractBatchDto(
            communicationPackageId: 1,
            environmentId: 5,
            clients: [999],
            price: 15.0,
            currency: 'USD',
        );
        $package = $this->package(1);
        $environment = $this->createMock(Environment::class);

        $this->targetAccountResolver->method('resolve')->willReturn([]);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationPackage::class, $this->repoReturning($package)],
            [Environment::class, $this->repoReturning($environment)],
        ]);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->createBatch($dto);

        $this->assertSame(0, $result->created);
        $this->assertSame([], $result->accountIds);
    }

    public function testCreateByRangeInterpolatesPriceLinearlyAcrossMatchingPackages(): void
    {
        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 100.0,
            toAmount: 200.0,
            step: 50.0,
            destinationCurrency: 'CUP',
            priceFrom: 10.0,
            priceTo: 20.0,
            currency: 'USD',
        );

        $p100 = $this->package(1);
        $p150 = $this->package(2);
        $p200 = $this->package(3);

        $this->packageRepository->method('findByDestination')->willReturnMap([
            [100.0, 'CUP', $p100],
            [150.0, 'CUP', $p150],
            [200.0, 'CUP', $p200],
        ]);

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturnMap([[CommunicationContract::class, $contractRepo]]);

        $nextId = 100;
        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($e) use (&$nextId, &$persisted) {
            $this->assignId($e, $nextId++);
            $persisted[] = $e;
        });
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createByRange($dto);

        $this->assertSame(3, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->skipped);
        $this->assertSame([], $result->skippedAmounts);
        $this->assertCount(3, $persisted);
        $this->assertSame(10.0, $persisted[0]->getPrice());
        $this->assertSame(15.0, $persisted[1]->getPrice());
        $this->assertSame(20.0, $persisted[2]->getPrice());
        $this->assertSame('USD', $persisted[0]->getCurrency());
    }

    public function testCreateByRangeSkipsAmountsWithoutMatchingPackageAndReportsThem(): void
    {
        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 100.0,
            toAmount: 300.0,
            step: 100.0,
            destinationCurrency: 'CUP',
            priceFrom: 10.0,
            priceTo: 30.0,
            currency: 'USD',
        );

        $p100 = $this->package(1);
        $p300 = $this->package(3);

        $this->packageRepository->method('findByDestination')->willReturnCallback(
            static fn (float $amount) => match ($amount) {
                100.0 => $p100,
                300.0 => $p300,
                default => null,
            }
        );

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturnMap([[CommunicationContract::class, $contractRepo]]);
        $this->em->method('persist')->willReturnCallback(fn ($e) => $this->assignId($e, 1));
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createByRange($dto);

        $this->assertSame(2, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertSame([200.0], $result->skippedAmounts);
    }

    public function testCreateByRangeThrowsWhenRangeExceedsMaxSize(): void
    {
        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 1.0,
            toAmount: 1000.0,
            step: 1.0,
            destinationCurrency: 'CUP',
            priceFrom: 1.0,
            priceTo: 2.0,
            currency: 'USD',
        );

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('máximo permitido');

        $this->service->createByRange($dto);
    }

    public function testCreateByRangeThrowsWhenTenantNotFound(): void
    {
        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 100.0,
            toAmount: 100.0,
            step: 50.0,
            destinationCurrency: 'CUP',
            priceFrom: 10.0,
            priceTo: 10.0,
            currency: 'USD',
            tenantId: 99,
            environmentId: 5,
        );
        $environment = $this->createMock(Environment::class);

        $this->em->method('getRepository')->with(Environment::class)->willReturn($this->repoReturning($environment));
        $this->targetAccountResolver->method('resolveOne')->willReturn(null);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Tenant not found');

        $this->service->createByRange($dto);
    }

    public function testCreateByRangeThrowsWhenEnvironmentIdMissingAndTenantGiven(): void
    {
        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 100.0,
            toAmount: 100.0,
            step: 50.0,
            destinationCurrency: 'CUP',
            priceFrom: 10.0,
            priceTo: 10.0,
            currency: 'USD',
            tenantId: 99,
        );

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('environmentId is required');

        $this->service->createByRange($dto);
    }

    public function testCreateByRangeWithSinglePointRangeUsesPriceFrom(): void
    {
        $dto = new CreateCommunicationContractRangeDto(
            fromAmount: 500.0,
            toAmount: 500.0,
            step: 50.0,
            destinationCurrency: 'CUP',
            priceFrom: 12.5,
            priceTo: 99.0,
            currency: 'USD',
        );

        $package = $this->package(1);
        $this->packageRepository->method('findByDestination')->willReturn($package);

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturnMap([[CommunicationContract::class, $contractRepo]]);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($e) use (&$persisted) {
            $this->assignId($e, 1);
            $persisted[] = $e;
        });
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createByRange($dto);

        $this->assertSame(1, $result->created);
        $this->assertSame(12.5, $persisted[0]->getPrice());
    }

    public function testUpdateOnlyTouchesProvidedFields(): void
    {
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($this->package(1))
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');

        $dto = new UpdateCommunicationContractDto(price: 12.0);

        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->update($contract, $dto);

        $this->assertSame(12.0, $updated->getPrice());
        $this->assertSame('USD', $updated->getCurrency());
    }

    /**
     * start_at es una columna `timestamp without time zone`: si se
     * persistiera el DateTimeImmutable con el offset original (ej.
     * "-04:00" de Cuba) en vez de normalizarlo a UTC, Doctrine relee esos
     * mismos dígitos asumiendo UTC y la hora mostrada en el frontend se
     * desplaza. Este test fija el reloj del sistema a UTC (como en
     * producción) y verifica que el string de entrada con offset -04:00 se
     * traduce a su instante UTC equivalente antes de guardarse.
     */
    public function testUpdateNormalizesStartAtOffsetToUtcBeforePersisting(): void
    {
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($this->package(1))
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');

        $dto = new UpdateCommunicationContractDto(startAt: '2026-08-12T20:00:00-04:00');

        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->update($contract, $dto);

        $this->assertSame('UTC', $updated->getStartAt()?->getTimezone()->getName());
        $this->assertSame('2026-08-13T00:00:00+00:00', $updated->getStartAt()?->format('c'));
    }

    public function testUpdateReassignsPackageByDestinationAmount(): void
    {
        $oldPackage = $this->package(1);
        $newPackage = $this->package(2);
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($oldPackage)
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');

        $dto = new UpdateCommunicationContractDto(destinationAmount: 750.0);

        $this->packageRepository->expects($this->once())
            ->method('findByDestination')
            ->with(750.0, 'CUP')
            ->willReturn($newPackage);

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturnMap([[CommunicationContract::class, $contractRepo]]);

        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->update($contract, $dto);

        $this->assertSame($newPackage, $updated->getCommunicationPackage());
    }

    public function testUpdateThrowsWhenDestinationAmountHasNoMatchingPackage(): void
    {
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($this->package(1))
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');

        $dto = new UpdateCommunicationContractDto(destinationAmount: 999.0);

        $this->packageRepository->method('findByDestination')->willReturn(null);
        $this->em->expects($this->never())->method('flush');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('No existe ningún paquete');

        $this->service->update($contract, $dto);
    }

    public function testUpdateThrowsWhenTargetPackageAlreadyHasOpenContractForTenant(): void
    {
        $tenant = $this->createMock(Account::class);
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($this->package(1))
            ->setTenant($tenant)
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');

        $newPackage = $this->package(2);
        $existingConflict = $this->createMock(CommunicationContract::class);

        $dto = new UpdateCommunicationContractDto(destinationAmount: 750.0);

        $this->packageRepository->method('findByDestination')->willReturn($newPackage);

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->method('findOneBy')->willReturn($existingConflict);
        $this->em->method('getRepository')->willReturnMap([[CommunicationContract::class, $contractRepo]]);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Ya existe un contrato abierto');

        $this->service->update($contract, $dto);
    }

    public function testCloseSetsEndAtAndFlushes(): void
    {
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($this->package(1))
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');
        $this->assertNull($contract->getEndAt());

        $this->em->expects($this->once())->method('flush');

        $this->service->close($contract);

        $this->assertNotNull($contract->getEndAt());
    }

    public function testDeleteRemovesAndFlushes(): void
    {
        $contract = (new CommunicationContract())
            ->setCommunicationPackage($this->package(1))
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice(10.0)->setCurrency('USD');

        $this->em->expects($this->once())->method('remove')->with($contract);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($contract);
    }

    public function testDeleteAllWithoutTenantRemovesEveryContract(): void
    {
        $c1 = (new CommunicationContract())->setCommunicationPackage($this->package(1))->setDestinationAmount(500.0)->setDestinationCurrency('CUP')->setPrice(1.0)->setCurrency('USD');
        $c2 = (new CommunicationContract())->setCommunicationPackage($this->package(2))->setDestinationAmount(600.0)->setDestinationCurrency('CUP')->setPrice(2.0)->setCurrency('USD');

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->expects($this->once())->method('findBy')->with([])->willReturn([$c1, $c2]);
        $this->em->method('getRepository')->with(CommunicationContract::class)->willReturn($contractRepo);

        $removed = [];
        $this->em->method('remove')->willReturnCallback(function ($e) use (&$removed) { $removed[] = $e; });
        $this->em->expects($this->once())->method('flush');

        $deleted = $this->service->deleteAll(null, null);

        $this->assertSame(2, $deleted);
        $this->assertSame([$c1, $c2], $removed);
    }

    public function testDeleteAllWithTenantOnlyRemovesThatTenantsContracts(): void
    {
        $tenant = $this->createMock(Account::class);
        $environment = $this->createMock(Environment::class);
        $contract = (new CommunicationContract())->setCommunicationPackage($this->package(1))->setDestinationAmount(500.0)->setDestinationCurrency('CUP')->setPrice(1.0)->setCurrency('USD');

        $contractRepo = $this->createMock(EntityRepository::class);
        $contractRepo->expects($this->once())->method('findBy')->with(['tenant' => $tenant])->willReturn([$contract]);

        $this->em->method('getRepository')->willReturnMap([
            [Environment::class, $this->repoReturning($environment)],
            [CommunicationContract::class, $contractRepo],
        ]);
        $this->targetAccountResolver->method('resolveOne')->with($environment, 5)->willReturn($tenant);

        $this->em->expects($this->once())->method('remove')->with($contract);
        $this->em->expects($this->once())->method('flush');

        $deleted = $this->service->deleteAll(5, 9);

        $this->assertSame(1, $deleted);
    }

    public function testDeleteAllThrowsWhenEnvironmentIdMissingAndTenantGiven(): void
    {
        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('environmentId is required');

        $this->service->deleteAll(5, null);
    }

    public function testDeleteAllThrowsWhenTenantNotFound(): void
    {
        $environment = $this->createMock(Environment::class);
        $this->em->method('getRepository')->with(Environment::class)->willReturn($this->repoReturning($environment));
        $this->targetAccountResolver->method('resolveOne')->willReturn(null);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Tenant not found');

        $this->service->deleteAll(5, 9);
    }
}
