<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPackage;
use App\Entity\Environment;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\CatalogVersionResolver;
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
    private PackageMaterializationService&MockObject $materializationService;
    private Security&MockObject $security;
    private PackageSalePriceResolver&MockObject $salePriceResolver;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private CatalogVersionResolver $catalogVersion;
    private ProviderInterface&MockObject $catalogProvider;
    private CommunicationClientPackageProvider $provider;

    protected function setUp(): void
    {
        $this->itemProvider = $this->createMock(ProviderInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->materializationService = $this->createMock(PackageMaterializationService::class);
        $this->security = $this->createMock(Security::class);
        $this->salePriceResolver = $this->createMock(PackageSalePriceResolver::class);
        // CatalogVersionResolver es `final` — no se puede mockear con
        // createMock(), se construye real con su única dependencia
        // (SysConfigRepository) mockeada. Por defecto isV2() da false (sin
        // valor cacheado), igual que en producción sin el flag seteado.
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->catalogVersion = new CatalogVersionResolver($this->sysConfigRepo);
        $this->catalogProvider = $this->createMock(ProviderInterface::class);

        $this->provider = new CommunicationClientPackageProvider(
            $this->itemProvider,
            $this->em,
            $this->materializationService,
            $this->security,
            $this->salePriceResolver,
            $this->catalogVersion,
            $this->catalogProvider,
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
        $this->em->expects($this->once())->method('flush');
        $this->salePriceResolver->expects($this->once())->method('resolveMany');

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide($operation);

        $this->assertSame($materialized, $result);
    }

    public function testDoesNotFlushOrReQueryWhenThereAreNoReferencesToMaterialize(): void
    {
        // Fase 1 de la deprecación de V1: se retiró la red de seguridad de
        // plantillas legacy (CommunicationPricePackage.tenant IS NULL) — sin
        // referencias, materializedCount queda en 0 y no hay nada más que
        // intentar.
        $tenant = $this->accountWithEnvironment();
        $this->security->method('getUser')->willReturn($tenant);

        $empty = $this->countableCollection([]);
        // itemProvider->provide() se llama dos veces: la consulta inicial, y
        // la re-consulta tras intentar materializar (sin importar si
        // materializedCount terminó en 0) — ver CommunicationClientPackageProvider::provide().
        $this->itemProvider->expects($this->exactly(2))->method('provide')->willReturn($empty);

        $clientPackageRepo = $this->createMock(CommunicationClientPackageRepository::class);
        $clientPackageRepo->method('findReferencePackages')->willReturn([]);
        $this->em->method('getRepository')->with(CommunicationClientPackage::class)->willReturn($clientPackageRepo);

        $this->materializationService->expects($this->never())->method('materializeForTenant');
        $this->em->expects($this->never())->method('flush');
        $this->salePriceResolver->expects($this->once())->method('resolveMany');

        $result = $this->provider->provide($this->createMock(Operation::class));

        $this->assertSame($empty, $result);
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

    public function testDelegatesToCatalogProviderWhenAccountIsV2(): void
    {
        $tenant = $this->accountWithEnvironment();
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')
            ->with(CatalogVersionResolver::DEFAULT_VERSION_KEY)
            ->willReturn('v2');

        $v2Packages = [$this->createMock(CommunicationPackage::class)];
        $this->catalogProvider->expects($this->once())->method('provide')->willReturn($v2Packages);

        $this->itemProvider->expects($this->never())->method('provide');
        $this->materializationService->expects($this->never())->method('materializeForTenant');
        $this->salePriceResolver->expects($this->never())->method('resolveMany');

        $result = $this->provider->provide($this->createMock(Operation::class));

        $this->assertSame($v2Packages, $result);
    }

    public function testKeepsLegacyPathWhenAccountIsNotV2(): void
    {
        $tenant = $this->accountWithEnvironment();
        $this->security->method('getUser')->willReturn($tenant);
        $this->sysConfigRepo->method('findCachedValue')->willReturn(null);

        $collection = $this->countableCollection([$this->createMock(CommunicationClientPackage::class)]);
        $this->itemProvider->expects($this->once())->method('provide')->willReturn($collection);
        $this->catalogProvider->expects($this->never())->method('provide');
        $this->salePriceResolver->expects($this->once())->method('resolveMany');

        $result = $this->provider->provide($this->createMock(Operation::class));

        $this->assertSame($collection, $result);
    }
}
