<?php

namespace App\Tests\Service\Pricing;

use App\DTO\CreateFixedContractDto;
use App\DTO\CreateRateContractDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Service\Pricing\PackageContractService;
use App\Service\Pricing\TargetAccountResolver;
use App\Service\Provider\CurrencyConversionService;
use App\Service\Provider\ResolvedExchangeRate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Pricing\PackageContractService
 */
class PackageContractServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TargetAccountResolver&MockObject $targetAccountResolver;
    private CurrencyConversionService&MockObject $currencyConversionService;
    private PackageContractService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->targetAccountResolver = $this->createMock(TargetAccountResolver::class);
        $this->currencyConversionService = $this->createMock(CurrencyConversionService::class);

        $this->service = new PackageContractService(
            $this->em,
            $this->targetAccountResolver,
            $this->currencyConversionService,
        );
    }

    private function reference(int $id): CommunicationClientPackage
    {
        $reference = (new CommunicationClientPackage())
            ->setName('Referencia')
            ->setDescription('Referencia')
            ->setProduct($this->createMock(CommunicationProduct::class));
        $this->assignId($reference, $id);

        return $reference;
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

    public function testCreateFixedThrowsWhenTenantNotFound(): void
    {
        $dto = new CreateFixedContractDto(tenantId: 99, referencePackageId: 1, amount: 10.0, currency: 'USD');

        $this->em->method('getRepository')->willReturnMap([
            [Account::class, $this->repoReturning(null)],
        ]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Tenant not found');

        $this->service->createFixed($dto);
    }

    public function testCreateFixedThrowsWhenReferenceIsNotAGenuineTemplate(): void
    {
        // referencePackageId apunta a un paquete que YA pertenece a un
        // cliente (tenant != null) — no es una referencia válida.
        $dto = new CreateFixedContractDto(tenantId: 1, referencePackageId: 1, amount: 10.0, currency: 'USD');

        $tenant = $this->createMock(Account::class);
        $notAReference = (new CommunicationClientPackage())->setTenant($this->createMock(Account::class));

        $this->em->method('getRepository')->willReturnMap([
            [Account::class, $this->repoReturning($tenant)],
            [CommunicationClientPackage::class, $this->repoReturning($notAReference)],
        ]);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('no es una referencia');

        $this->service->createFixed($dto);
    }

    public function testCreateFixedPersistsANewContractWithFixedMode(): void
    {
        $dto = new CreateFixedContractDto(tenantId: 1, referencePackageId: 1, amount: 12.5, currency: 'USD', name: 'Precio VIP');

        $tenant = $this->createMock(Account::class);
        $reference = $this->reference(1);

        $pricePackageRepo = $this->createMock(EntityRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn(null); // sin contrato previo

        $this->em->method('getRepository')->willReturnMap([
            [Account::class, $this->repoReturning($tenant)],
            [CommunicationClientPackage::class, $this->repoReturning($reference)],
            [CommunicationPricePackage::class, $pricePackageRepo],
        ]);

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(CommunicationPricePackage::class));
        $this->em->expects($this->once())->method('flush');

        $contract = $this->service->createFixed($dto);

        $this->assertSame('FIXED', $contract->getContractMode());
        $this->assertSame(12.5, $contract->getAmount());
        $this->assertSame('USD', $contract->getCurrency());
        $this->assertSame('Precio VIP', $contract->getName());
        $this->assertTrue($contract->isContract());
        $this->assertSame($tenant, $contract->getTenant());
        $this->assertSame($reference, $contract->getReferencePackage());
    }

    public function testCreateFixedUpdatesExistingContractInsteadOfDuplicating(): void
    {
        $dto = new CreateFixedContractDto(tenantId: 1, referencePackageId: 1, amount: 30.0, currency: 'USD');

        $tenant = $this->createMock(Account::class);
        $reference = $this->reference(1);
        $existing = (new CommunicationPricePackage())->setTenant($tenant)->setReferencePackage($reference)
            ->setIsContract(true)->setContractMode('FIXED')->setAmount(10.0)->setCurrency('USD');

        $pricePackageRepo = $this->createMock(EntityRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn($existing);

        $this->em->method('getRepository')->willReturnMap([
            [Account::class, $this->repoReturning($tenant)],
            [CommunicationClientPackage::class, $this->repoReturning($reference)],
            [CommunicationPricePackage::class, $pricePackageRepo],
        ]);

        $this->em->expects($this->never())->method('persist');

        $contract = $this->service->createFixed($dto);

        $this->assertSame($existing, $contract);
        $this->assertSame(30.0, $contract->getAmount());
    }

    public function testCreateByRateWithEmptyClientsAppliesToAllAccountsFromTargetResolver(): void
    {
        $dto = new CreateRateContractDto(
            referencePackageId: 1,
            basePrice: 100.0,
            baseCurrency: 'USD',
            rateValue: 1.15,
            currency: 'USD',
            environmentId: 5,
            clients: [],
        );

        $reference = $this->reference(1);
        $environment = $this->createMock(Environment::class);
        $account1 = $this->createMock(Account::class);
        $account1->method('getId')->willReturn(101);
        $account2 = $this->createMock(Account::class);
        $account2->method('getId')->willReturn(102);

        $this->targetAccountResolver->expects($this->once())
            ->method('resolve')
            ->with($environment, [])
            ->willReturn([$account1, $account2]);

        $pricePackageRepo = $this->createMock(EntityRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $this->repoReturning($reference)],
            [Environment::class, $this->repoReturning($environment)],
            [CommunicationPricePackage::class, $pricePackageRepo],
        ]);

        $this->currencyConversionService->expects($this->never())->method('getRateDetails');

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($e) use (&$persisted) { $persisted[] = $e; });
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createByRate($dto);

        $this->assertSame(2, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame([101, 102], $result->accountIds);
        $this->assertCount(2, $persisted);
        foreach ($persisted as $contract) {
            $this->assertSame('RATE', $contract->getContractMode());
            $this->assertSame(115.0, $contract->getAmount()); // round(100 * 1.15, 2)
            $this->assertSame(100.0, $contract->getBasePrice());
            $this->assertSame(1.15, $contract->getRateValue());
        }
    }

    public function testCreateByRateAppliesCurrencyConversionWhenBaseCurrencyDiffersFromTarget(): void
    {
        $dto = new CreateRateContractDto(
            referencePackageId: 1,
            basePrice: 500.0,
            baseCurrency: 'CUP',
            rateValue: 0.02,
            currency: 'USD',
            environmentId: 5,
            clients: [10],
        );

        $reference = $this->reference(1);
        $environment = $this->createMock(Environment::class);
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(10);

        $this->targetAccountResolver->method('resolve')->willReturn([$account]);

        $pricePackageRepo = $this->createMock(EntityRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $this->repoReturning($reference)],
            [Environment::class, $this->repoReturning($environment)],
            [CommunicationPricePackage::class, $pricePackageRepo],
        ]);

        // raw = round(500 * 0.02, 2) = 10.0 CUP; convertido a USD con tasa 0.04.
        $this->currencyConversionService->expects($this->once())
            ->method('getRateDetails')
            ->with('CUP', 'USD')
            ->willReturn(new ResolvedExchangeRate(0.04, new \DateTimeImmutable('2026-08-01')));

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($e) use (&$persisted) { $persisted[] = $e; });
        $this->em->method('flush');

        $result = $this->service->createByRate($dto);

        $this->assertSame(1, $result->created);
        $this->assertSame(0.4, $persisted[0]->getAmount()); // round(10.0 * 0.04, 2)
        $this->assertSame('USD', $persisted[0]->getCurrency());
        $this->assertSame(0.04, $persisted[0]->getConversionRate());
    }

    public function testCreateByRateReturnsZeroWithoutFlushingWhenNoAccountsMatch(): void
    {
        $dto = new CreateRateContractDto(
            referencePackageId: 1,
            basePrice: 100.0,
            baseCurrency: 'USD',
            rateValue: 1.1,
            currency: 'USD',
            environmentId: 5,
            clients: [999],
        );

        $reference = $this->reference(1);
        $environment = $this->createMock(Environment::class);

        $this->targetAccountResolver->method('resolve')->willReturn([]);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $this->repoReturning($reference)],
            [Environment::class, $this->repoReturning($environment)],
        ]);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->createByRate($dto);

        $this->assertSame(0, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame([], $result->accountIds);
    }
}
