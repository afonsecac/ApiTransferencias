<?php

namespace App\Tests\Service\Catalog;

use App\Entity\Account;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationContractRepository;
use App\Repository\CommunicationPackageRepository;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\ContractGatingScopeResolver;
use App\Service\Catalog\UpcomingPackageCatalogResolver;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Catalog\UpcomingPackageCatalogResolver
 *
 * Todos los tests corren con ContractGatingScopeResolver devolviendo
 * `false` (alcance tenant, default) salvo el bloque "alcance category" al
 * final — mismo criterio que PackageCatalogResolverTest.
 */
class UpcomingPackageCatalogResolverTest extends TestCase
{
    private CommunicationPackageRepository&MockObject $packageRepository;
    private CommunicationContractRepository&MockObject $contractRepository;
    private BenefitOperationResolver&MockObject $benefitResolver;
    private SysConfigRepository&MockObject $gatingSysConfigRepo;
    private ContractGatingScopeResolver $gatingScopeResolver;
    private UpcomingPackageCatalogResolver $resolver;

    protected function setUp(): void
    {
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);
        $this->contractRepository = $this->createMock(CommunicationContractRepository::class);
        $this->benefitResolver = $this->createMock(BenefitOperationResolver::class);
        $this->benefitResolver->method('resolve')->willReturnCallback(static fn (CommunicationPackage $p) => $p->getBenefits());

        // ContractGatingScopeResolver es `final` — instancia real sobre un
        // SysConfigRepository mockeado propio, mismo criterio (y misma
        // razón para NO poner un willReturn(null) de relleno acá) que
        // PackageCatalogResolverTest.
        $this->gatingSysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->gatingScopeResolver = new ContractGatingScopeResolver($this->gatingSysConfigRepo);

        $this->resolver = new UpcomingPackageCatalogResolver($this->packageRepository, $this->contractRepository, $this->benefitResolver, $this->gatingScopeResolver);
    }

    private function enableCategoryScope(): void
    {
        $this->gatingSysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ContractGatingScopeResolver::SCOPE_KEY ? 'category' : null);
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function package(int $id): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName("Promo {$id}")->setDescription("Promo {$id}")
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP');
        $this->assignId($package, $id);

        return $package;
    }

    private function contract(?Account $tenant, float $price, \DateTimeImmutable $startAt, string $currency = 'USD'): CommunicationContract
    {
        $contract = (new CommunicationContract())
            ->setTenant($tenant)
            ->setDestinationAmount(500.0)->setDestinationCurrency('CUP')
            ->setPrice($price)->setCurrency($currency)
            ->setStartAt($startAt);
        $this->assignId($contract, random_int(1000, 999999));

        return $contract;
    }

    private function packageInCategory(int $id, string $serviceName, string $subserviceName): CommunicationPackage
    {
        $package = $this->package($id);
        $package->setService(['name' => $serviceName, 'subservice' => ['name' => $subserviceName]]);

        return $package;
    }

    private function contractInCategory(?Account $tenant, float $price, \DateTimeImmutable $startAt, string $serviceName, string $subserviceName): CommunicationContract
    {
        $contract = $this->contract($tenant, $price, $startAt);
        $contract->setServiceCategory($serviceName, $subserviceName);

        return $contract;
    }

    /**
     * $contract->addPackage($package) solo actualiza el lado propietario
     * (CommunicationContract::$packages) — el lado inverso
     * (CommunicationPackage::$contracts, mappedBy) solo lo resuelve Doctrine
     * vía SQL al hidratar desde una consulta real. En un test unitario sin
     * EntityManager hay que sincronizarlo a mano (mismo patrón que
     * CommunicationPackageAdminServiceTest::addContractTo() en la Fase 6).
     */
    private function linkContractToPackage(CommunicationContract $contract, CommunicationPackage $package): void
    {
        $contract->addPackage($package);

        $property = new \ReflectionProperty($package, 'contracts');
        $property->setAccessible(true);
        $property->getValue($package)->add($contract);
    }

    public function testReturnsEmptyArrayWhenNoUpcomingPackages(): void
    {
        $tenant = $this->createMock(Account::class);
        $this->packageRepository->method('findUpcoming')->willReturn([]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([]);

        $this->assertSame([], $this->resolver->upcomingFor($tenant));
    }

    public function testSkipsPackagesWithoutAnyContract(): void
    {
        $tenant = $this->createMock(Account::class);
        $tenant->method('getId')->willReturn(1);
        $package = $this->package(1);

        $this->packageRepository->method('findUpcoming')->willReturn([$package]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([]);

        $this->assertSame([], $this->resolver->upcomingFor($tenant));
    }

    public function testPrefersTheTenantsOwnContractOverTheDefaultOne(): void
    {
        $tenant = $this->createMock(Account::class);
        $tenant->method('getId')->willReturn(1);
        $package = $this->package(1);

        $now = new \DateTimeImmutable();
        $default = $this->contract(null, 10.0, $now->modify('+1 day'));
        $own = $this->contract($tenant, 8.0, $now->modify('+1 day'));
        $this->linkContractToPackage($default, $package);
        $this->linkContractToPackage($own, $package);

        $this->packageRepository->method('findUpcoming')->willReturn([$package]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([]);

        $result = $this->resolver->upcomingFor($tenant);

        $this->assertCount(1, $result);
        $this->assertSame(8.0, $result[0]->getAmount());
        $this->assertSame(PackageOfferSourceEnum::PROMOTION, $result[0]->getResolvedOffer()?->source);
        $this->assertSame($own->getId(), $result[0]->getResolvedOffer()?->contractId);
    }

    public function testUsesTheDefaultContractWhenTenantHasNoneOfItsOwn(): void
    {
        $tenant = $this->createMock(Account::class);
        $tenant->method('getId')->willReturn(1);
        $package = $this->package(1);

        $now = new \DateTimeImmutable();
        $default = $this->contract(null, 12.0, $now->modify('+1 day'));
        $this->linkContractToPackage($default, $package);

        $this->packageRepository->method('findUpcoming')->willReturn([$package]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([]);

        $result = $this->resolver->upcomingFor($tenant);

        $this->assertCount(1, $result);
        $this->assertSame(12.0, $result[0]->getAmount());
        $this->assertSame('USD', $result[0]->getCurrency());
    }

    public function testSkipsPackageWhenTenantHasOwnActiveContractsButNoneCoversIt(): void
    {
        $tenant = $this->createMock(Account::class);
        $tenant->method('getId')->willReturn(1);
        $upcomingPackage = $this->package(1);
        $otherPackage = $this->package(2);

        $now = new \DateTimeImmutable();
        $default = $this->contract(null, 12.0, $now->modify('+1 day'));
        $this->linkContractToPackage($default, $upcomingPackage);

        // La cuenta tiene un contrato propio activo, pero para OTRO
        // paquete — no cubre el que está por arrancar.
        $ownActiveContract = $this->contract($tenant, 5.0, $now->modify('-1 day'));
        $this->linkContractToPackage($ownActiveContract, $otherPackage);

        $this->packageRepository->method('findUpcoming')->willReturn([$upcomingPackage]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([$ownActiveContract]);

        $this->assertSame([], $this->resolver->upcomingFor($tenant));
    }

    public function testShowsPreviewWhenTenantsOwnActiveContractAlreadyCoversTheUpcomingPackage(): void
    {
        $tenant = $this->createMock(Account::class);
        $tenant->method('getId')->willReturn(1);
        $package = $this->package(1);

        $now = new \DateTimeImmutable();
        // El mismo contrato propio activo YA cubre este paquete (ej. el
        // paquete comparte tupla con el catálogo normal del tenant) — no se
        // omite.
        $ownActiveContract = $this->contract($tenant, 9.0, $now->modify('-1 day'));
        $this->linkContractToPackage($ownActiveContract, $package);

        $this->packageRepository->method('findUpcoming')->willReturn([$package]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([$ownActiveContract]);

        $result = $this->resolver->upcomingFor($tenant);

        $this->assertCount(1, $result);
        $this->assertSame(9.0, $result[0]->getAmount());
    }

    // ---- Fase 2: alcance CATEGORY (ContractGatingScopeResolver::isCategoryScoped() === true) ----

    public function testCategoryScopeStillShowsPreviewWhenPackageIsInACategoryWithoutAnyTenantContract(): void
    {
        // El tenant tiene contrato propio activo en Mobile/Airtime (otro
        // paquete), pero el paquete próximo a arrancar es Utilities/Internet
        // — categoría sin ningún contrato propio del tenant, así que NO
        // debe gatearse: cae al contrato "por defecto" de su categoría,
        // igual que si el tenant no tuviera contrato en absoluto.
        $this->enableCategoryScope();

        $tenant = $this->createMock(Account::class);
        $tenant->method('getId')->willReturn(1);

        $otherPackage = $this->packageInCategory(1, 'Mobile', 'Airtime');
        $ownActiveContract = $this->contractInCategory($tenant, 5.0, new \DateTimeImmutable('-1 day'), 'Mobile', 'Airtime');
        $this->linkContractToPackage($ownActiveContract, $otherPackage);

        $upcomingPackage = $this->packageInCategory(2, 'Utilities', 'Internet');
        $default = $this->contractInCategory(null, 12.0, new \DateTimeImmutable('+1 day'), 'Utilities', 'Internet');
        $this->linkContractToPackage($default, $upcomingPackage);

        $this->packageRepository->method('findUpcoming')->willReturn([$upcomingPackage]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([$ownActiveContract]);

        $result = $this->resolver->upcomingFor($tenant);

        $this->assertCount(1, $result);
        $this->assertSame(12.0, $result[0]->getAmount());
    }

    public function testCategoryScopeSkipsPackageWhenItsOwnCategoryIsGatedByTenantAndNotCovered(): void
    {
        // No-regresión: dentro de una categoría donde el tenant SÍ tiene
        // contrato propio, la regla sigue siendo "todo o nada" — el alcance
        // category solo la acota a esa categoría, no la debilita.
        $this->enableCategoryScope();

        $tenant = $this->createMock(Account::class);
        $tenant->method('getId')->willReturn(1);

        $upcomingPackage = $this->packageInCategory(1, 'Mobile', 'Airtime');
        $otherPackage = $this->packageInCategory(2, 'Mobile', 'Airtime');

        $default = $this->contractInCategory(null, 12.0, new \DateTimeImmutable('+1 day'), 'Mobile', 'Airtime');
        $this->linkContractToPackage($default, $upcomingPackage);

        $ownActiveContract = $this->contractInCategory($tenant, 5.0, new \DateTimeImmutable('-1 day'), 'Mobile', 'Airtime');
        $this->linkContractToPackage($ownActiveContract, $otherPackage);

        $this->packageRepository->method('findUpcoming')->willReturn([$upcomingPackage]);
        $this->contractRepository->method('findActiveForTenant')->willReturn([$ownActiveContract]);

        $this->assertSame([], $this->resolver->upcomingFor($tenant));
    }
}
