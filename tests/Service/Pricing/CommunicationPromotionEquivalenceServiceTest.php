<?php

namespace App\Tests\Service\Pricing;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderPromotionCatalogInterface;
use App\Provider\Contract\ProviderProductDto;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationPackageRepository;
use App\Repository\CommunicationProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\Pricing\CommunicationPromotionEquivalenceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Pricing\CommunicationPromotionEquivalenceService
 *
 * ProviderRegistry es `final` — no se mockea, se construye real con la
 * lista de proveedores fake que necesita cada test (mismo patrón que
 * DTOneHttpClientTest::credentialsResolver()).
 */
class CommunicationPromotionEquivalenceServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationPackageProviderProductRepository&MockObject $bindingRepo;
    private CommunicationProductRepository&MockObject $productRepository;
    private CommunicationPackageRepository&MockObject $packageRepository;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->productRepository = $this->createMock(CommunicationProductRepository::class);
        $this->packageRepository = $this->createMock(CommunicationPackageRepository::class);
    }

    /**
     * @param list<ProviderPromotionCatalogInterface> $providers
     */
    private function makeService(array $providers): CommunicationPromotionEquivalenceService
    {
        $registry = new ProviderRegistry($providers);
        $contextFactory = new ProviderContextFactory(new ProviderResolver(
            $this->createMock(SysConfigRepository::class),
            $this->createMock(ClientProviderRoutingRepository::class),
            new NullLogger(),
        ));

        return new CommunicationPromotionEquivalenceService(
            $this->em,
            $registry,
            $contextFactory,
            $this->bindingRepo,
            $this->productRepository,
            $this->packageRepository,
        );
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function promotion(): CommunicationPromotions
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getType')->willReturn('TEST');
        $environment->method('getId')->willReturn(4);

        return (new CommunicationPromotions())
            ->setName('Promo')
            ->setDescription('Promo')
            ->setEnvironment($environment)
            ->setStartAt(new \DateTimeImmutable('2026-08-18T00:00:00+00:00'))
            ->setEndAt(new \DateTimeImmutable('2026-08-25T23:59:00+00:00'));
    }

    private function package(int $id, float $amount): CommunicationPackage
    {
        $package = (new CommunicationPackage())
            ->setName("p{$id}")
            ->setDescription("p{$id}")
            ->setDestinationAmount($amount)
            ->setDestinationCurrency('CUP');
        $this->assignId($package, $id);

        return $package;
    }

    private function providerProduct(string $externalId, ?float $destinationAmount): ProviderProductDto
    {
        return new ProviderProductDto(
            externalId: $externalId,
            name: 'p',
            description: null,
            productTypeRaw: null,
            wholesalePrice: 1.0,
            priceCurrency: 'USD',
            destinationAmount: $destinationAmount,
            destinationMinAmount: null,
            destinationMaxAmount: null,
            destinationUnit: $destinationAmount !== null ? 'CUP' : null,
            benefits: [],
            enabled: true,
            validFrom: null,
            validTo: null,
            raw: [],
            isMobileOrInternetService: true,
            service: [],
        );
    }

    private function fakeProvider(CommunicationProviderEnum $code, iterable $products): ProviderPromotionCatalogInterface&MockObject
    {
        $provider = $this->createMock(ProviderPromotionCatalogInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('fetchPromotionProducts')->willReturn($products);

        return $provider;
    }

    public function testReturnsEmptyResultWithoutTouchingProvidersWhenNoPackagesGiven(): void
    {
        $provider = $this->createMock(ProviderPromotionCatalogInterface::class);
        $provider->method('getCode')->willReturn(CommunicationProviderEnum::DTONE);
        $provider->expects($this->never())->method('fetchPromotionProducts');

        $result = $this->makeService([$provider])->populateEquivalences($this->promotion(), []);

        $this->assertSame([], $result->providers);
        $this->assertSame([], $result->gaps);
    }

    public function testMatchesACandidateAgainstItsTramoAndCreatesTheBinding(): void
    {
        $packages = [$this->package(1, 500.0), $this->package(2, 525.0)];
        $product500 = $this->createMock(CommunicationProduct::class);

        $service = $this->makeService([
            $this->fakeProvider(CommunicationProviderEnum::DTONE, [$this->providerProduct('35719', 500.0)]),
        ]);
        $this->productRepository->method('findOneBy')
            ->with(['provider' => 'DTONE', 'externalRef' => '35719'])
            ->willReturn($product500);
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(CommunicationPackageProviderProduct::class));
        $this->em->expects($this->once())->method('flush');

        $result = $service->populateEquivalences($this->promotion(), $packages);

        $this->assertSame('DTONE', $result->providers[0]['provider']);
        $this->assertSame(1, $result->providers[0]['matched']);
        $this->assertNull($result->providers[0]['error']);

        // El tramo 525 no tiene candidato -> queda como hueco para DTONE.
        $this->assertCount(1, $result->gaps);
        $this->assertSame(2, $result->gaps[0]['packageId']);
        $this->assertSame(['DTONE'], $result->gaps[0]['missingProviders']);
    }

    /**
     * Bug real encontrado en pruebas E2E (2026-08-18): CSQ puede devolver
     * más de un candidato para el MISMO monto nominal (ej. catálogo con
     * duplicados). Sin esta guarda, dos upsertBinding() para la misma
     * (package, provider) sin flush de por medio violan
     * uniq_com_package_provider al hacer flush() al final — el primer
     * candidato que cubre el tramo debe ganar, los siguientes se ignoran.
     */
    public function testASecondCandidateForTheSameTramoAndProviderDoesNotDuplicateTheBinding(): void
    {
        $packages = [$this->package(1, 500.0)];
        $productA = $this->createMock(CommunicationProduct::class);
        $productB = $this->createMock(CommunicationProduct::class);

        $service = $this->makeService([
            $this->fakeProvider(CommunicationProviderEnum::CSQ, [
                $this->providerProduct('7854-500', 500.0),
                $this->providerProduct('7854-500-dup', 500.0),
            ]),
        ]);
        $this->productRepository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => match ($criteria['externalRef']) {
                '7854-500' => $productA,
                '7854-500-dup' => $productB,
                default => null,
            }
        );
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn(null);

        $this->em->expects($this->once())->method('persist');

        $result = $service->populateEquivalences($this->promotion(), $packages);

        $this->assertSame(1, $result->providers[0]['matched']);
        $this->assertSame([], $result->gaps);
    }

    public function testAFlexibleAmountCandidateCoversEveryTramo(): void
    {
        $packages = [$this->package(1, 500.0), $this->package(2, 525.0)];
        $product = $this->createMock(CommunicationProduct::class);

        $service = $this->makeService([
            $this->fakeProvider(CommunicationProviderEnum::ETECSA, [$this->providerProduct('100', null)]),
        ]);
        $this->productRepository->method('findOneBy')->willReturn($product);
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn(null);

        $result = $service->populateEquivalences($this->promotion(), $packages);

        $this->assertSame(2, $result->providers[0]['matched']);
        $this->assertSame([], $result->gaps);
    }

    public function testSkipsACandidateNotYetSyncedAsACommunicationProduct(): void
    {
        $packages = [$this->package(1, 500.0)];

        $service = $this->makeService([
            $this->fakeProvider(CommunicationProviderEnum::DTONE, [$this->providerProduct('99999', 500.0)]),
        ]);
        $this->productRepository->method('findOneBy')->willReturn(null);
        $this->em->expects($this->never())->method('persist');

        $result = $service->populateEquivalences($this->promotion(), $packages);

        $this->assertSame(0, $result->providers[0]['matched']);
        $this->assertSame(['DTONE'], $result->gaps[0]['missingProviders']);
    }

    public function testAProviderErrorIsCapturedWithoutAbortingOtherProviders(): void
    {
        $packages = [$this->package(1, 500.0)];
        $failing = $this->createMock(ProviderPromotionCatalogInterface::class);
        $failing->method('getCode')->willReturn(CommunicationProviderEnum::DTONE);
        $failing->method('fetchPromotionProducts')->willThrowException(new \RuntimeException('DTOne unavailable'));

        $product = $this->createMock(CommunicationProduct::class);
        $ok = $this->fakeProvider(CommunicationProviderEnum::CSQ, [$this->providerProduct('7854-500', 500.0)]);

        $service = $this->makeService([$failing, $ok]);
        $this->productRepository->method('findOneBy')->willReturn($product);
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn(null);

        $result = $service->populateEquivalences($this->promotion(), $packages);

        $this->assertSame('DTOne unavailable', $result->providers[0]['error']);
        $this->assertSame(0, $result->providers[0]['matched']);
        $this->assertSame('CSQ', $result->providers[1]['provider']);
        $this->assertSame(1, $result->providers[1]['matched']);
    }

    public function testDoesNotDuplicateAnExistingBindingAndCountsItAsAlreadyCovered(): void
    {
        $packages = [$this->package(1, 500.0)];
        $product = $this->createMock(CommunicationProduct::class);
        $existingBinding = $this->createMock(CommunicationPackageProviderProduct::class);
        $existingBinding->method('getProvider')->willReturn('DTONE');
        $existingBinding->expects($this->once())->method('setProduct')->with($product);

        $this->bindingRepo->method('findAllForPackage')->willReturn([$existingBinding]);
        $this->bindingRepo->method('findForPackageAndProvider')->willReturn($existingBinding);
        $service = $this->makeService([
            $this->fakeProvider(CommunicationProviderEnum::DTONE, [$this->providerProduct('35719', 500.0)]),
        ]);
        $this->productRepository->method('findOneBy')->willReturn($product);
        $this->em->expects($this->never())->method('persist');

        $result = $service->populateEquivalences($this->promotion(), $packages);

        // Ya estaba cubierto antes de correr -> no se cuenta como "matched" nuevo.
        $this->assertSame(0, $result->providers[0]['matched']);
        $this->assertSame([], $result->gaps);
    }

    public function testCoverageIsReadOnlyAndNeverCallsTheProvider(): void
    {
        $packages = [$this->package(1, 500.0)];
        $this->packageRepository->method('findByPromotion')->willReturn($packages);

        $provider = $this->createMock(ProviderPromotionCatalogInterface::class);
        $provider->method('getCode')->willReturn(CommunicationProviderEnum::DTONE);
        $provider->expects($this->never())->method('fetchPromotionProducts');

        $result = $this->makeService([$provider])->coverage($this->promotion());

        $this->assertSame([], $result->providers);
        $this->assertSame(['DTONE'], $result->gaps[0]['missingProviders']);
    }

    public function testRefreshForPromotionReloadsPackagesFromRepository(): void
    {
        $packages = [$this->package(1, 500.0)];
        $this->packageRepository->expects($this->once())->method('findByPromotion')->willReturn($packages);

        $result = $this->makeService([])->refreshForPromotion($this->promotion());

        $this->assertSame([], $result->providers);
        $this->assertSame([], $result->gaps);
    }
}
