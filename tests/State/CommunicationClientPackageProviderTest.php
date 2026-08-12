<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\Environment;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationPricePackageRepository;
use App\Service\PackagePriceService;
use App\Service\Pricing\PackageMaterializationService;
use App\Service\Pricing\PackageSalePriceResolver;
use App\State\CommunicationClientPackageProvider;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\CommunicationClientPackageProvider
 */
class CommunicationClientPackageProviderTest extends TestCase
{
    private ProviderInterface&MockObject $itemProvider;
    private EntityManagerInterface&MockObject $em;
    private PackagePriceService&MockObject $packagePriceService;
    private PackageMaterializationService&MockObject $materializationService;
    private Security&MockObject $security;
    private PackageSalePriceResolver&MockObject $salePriceResolver;
    private CommunicationClientPackageProvider $provider;

    protected function setUp(): void
    {
        $this->itemProvider = $this->createMock(ProviderInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->packagePriceService = $this->createMock(PackagePriceService::class);
        $this->materializationService = $this->createMock(PackageMaterializationService::class);
        $this->security = $this->createMock(Security::class);
        $this->salePriceResolver = $this->createMock(PackageSalePriceResolver::class);

        $this->provider = new CommunicationClientPackageProvider(
            $this->itemProvider,
            $this->em,
            $this->packagePriceService,
            $this->materializationService,
            $this->security,
            $this->salePriceResolver,
            $this->createMock(LoggerInterface::class),
        );
    }

    /** @param CommunicationClientPackage[] $items */
    private function countableCollection(array $items): \Countable&\IteratorAggregate
    {
        return new class($items) implements \Countable, \IteratorAggregate {
            public function __construct(private readonly array $items) {}
            public function count(): int { return count($this->items); }
            public function getIterator(): \Iterator { return new \ArrayIterator($this->items); }
        };
    }

    private function accountWithEnvironment(): Account&MockObject
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('TEST');

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(1);
        $account->method('getEnvironment')->willReturn($environment);

        return $account;
    }

    public function testDoesNotMaterializeWhenCollectionIsAlreadyNonEmpty(): void
    {
        $existing = $this->createMock(CommunicationClientPackage::class);
        $collection = $this->countableCollection([$existing]);

        $this->itemProvider->expects($this->once())->method('provide')->willReturn($collection);
        $this->security->method('getUser')->willReturn($this->accountWithEnvironment());

        $this->materializationService->expects($this->never())->method('materializeForTenant');
        $this->em->expects($this->never())->method('flush');
        $this->salePriceResolver->expects($this->once())->method('resolveMany');

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide($operation);

        $this->assertSame($collection, $result);
    }

    public function testMaterializesFromReferencesWhenCollectionIsEmpty(): void
    {
        $tenant = $this->accountWithEnvironment();
        $this->security->method('getUser')->willReturn($tenant);

        $empty = $this->countableCollection([]);
        $materialized = $this->countableCollection([$this->createMock(CommunicationClientPackage::class)]);
        $this->itemProvider->expects($this->exactly(2))->method('provide')
            ->willReturnOnConsecutiveCalls($empty, $materialized);

        $reference1 = $this->createMock(CommunicationClientPackage::class);
        $reference2 = $this->createMock(CommunicationClientPackage::class);
        $clientPackageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $clientPackageRepo->method('findReferencePackages')->willReturn([$reference1, $reference2]);
        $this->em->method('getRepository')->with(CommunicationClientPackage::class)->willReturn($clientPackageRepo);

        $this->materializationService->expects($this->exactly(2))->method('materializeForTenant');
        $this->packagePriceService->expects($this->never())->method('createPackageClient');
        $this->packagePriceService->expects($this->never())->method('copyPricePackage');
        $this->em->expects($this->once())->method('flush');
        $this->salePriceResolver->expects($this->once())->method('resolveMany');

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide($operation);

        $this->assertSame($materialized, $result);
    }

    public function testFallsBackToLegacyTemplatesWhenNoReferencesExist(): void
    {
        $tenant = $this->accountWithEnvironment();
        $this->security->method('getUser')->willReturn($tenant);

        $empty = $this->countableCollection([]);
        $materialized = $this->countableCollection([$this->createMock(CommunicationClientPackage::class)]);
        $this->itemProvider->expects($this->exactly(2))->method('provide')
            ->willReturnOnConsecutiveCalls($empty, $materialized);

        $clientPackageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $clientPackageRepo->method('findReferencePackages')->willReturn([]);

        $globalTemplate = $this->createMock(CommunicationPricePackage::class);
        $pricePackageRepo = $this->createMock(CommunicationPricePackageRepository::class);
        $pricePackageRepo->method('getPricesByEnvironment')->willReturnCallback(
            function (?string $env, ?int $tenantId = null) use ($globalTemplate) {
                return $tenantId === null ? [$globalTemplate] : [];
            }
        );

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationClientPackage::class, $clientPackageRepo],
            [CommunicationPricePackage::class, $pricePackageRepo],
        ]);

        $this->materializationService->expects($this->never())->method('materializeForTenant');
        $this->packagePriceService->expects($this->once())->method('copyPricePackage')->with($globalTemplate, $tenant);
        $this->em->expects($this->once())->method('flush');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation);
    }

    public function testRaceConditionOnUniqueConstraintIsCaughtAndDoesNotPropagate(): void
    {
        $tenant = $this->accountWithEnvironment();
        $this->security->method('getUser')->willReturn($tenant);

        $empty = $this->countableCollection([]);
        $materialized = $this->countableCollection([$this->createMock(CommunicationClientPackage::class)]);
        $this->itemProvider->expects($this->exactly(2))->method('provide')
            ->willReturnOnConsecutiveCalls($empty, $materialized);

        $reference = $this->createMock(CommunicationClientPackage::class);
        $clientPackageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $clientPackageRepo->method('findReferencePackages')->willReturn([$reference]);
        $this->em->method('getRepository')->with(CommunicationClientPackage::class)->willReturn($clientPackageRepo);

        $this->em->method('flush')->willThrowException(
            $this->createMock(UniqueConstraintViolationException::class)
        );

        // No debe propagar la excepción — otro request ya ganó la carrera.
        $result = $this->provider->provide($this->createMock(Operation::class));

        $this->assertSame($materialized, $result);
    }
}
