<?php

namespace App\Tests\Functional\Provider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\DTO\CreatePromotionV2Dto;
use App\Entity\SysConfig;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\CommunicationPromotionService;
use App\State\CommunicationClientPackageItemProvider;
use App\State\CommunicationClientPackageProvider;
use App\State\UpcomingPackagesProvider;

/**
 * @covers \App\State\CommunicationClientPackageProvider
 * @covers \App\State\CommunicationClientPackageItemProvider
 * @covers \App\State\UpcomingPackagesProvider
 *
 * Verificación end-to-end del switch V1/V2 de `/communication/packages`
 * contra Postgres real — el mock no puede validar que
 * CatalogVersionResolver realmente lee sys_config, ni que el shape
 * serializado real de ambas entidades coincide.
 */
class CommunicationPackagesV2SwitchFunctionalTest extends ProviderFunctionalTestCase
{
    private static int $counter = 0;

    /**
     * El caché de sys_config (SysConfigRepository) NO está atado a la
     * transacción de BD que FunctionalTestCase revierte en tearDown() — es
     * un pool aparte que sobrevive entre tests dentro del mismo proceso de
     * PHPUnit. Sin esto, un valor cacheado durante este test (ej.
     * communications.catalog.version.default = 'v2') podía quedar vigente
     * para OTRO test que corre después en la misma suite, aunque la fila de
     * sys_config ya se haya revertido — así se detectó
     * CommunicationSaleServiceProviderGuardFunctionalTest fallando solo en
     * la suite completa, nunca en aislamiento.
     */
    protected function tearDown(): void
    {
        // Antes de que parent::tearDown() haga rollback y potencialmente
        // apague el kernel — self::getContainer() debe llamarse mientras
        // el kernel siga arriba.
        self::getContainer()->get(SysConfigRepository::class)->invalidateCache();
        parent::tearDown();
    }

    private function sysConfigRepo(): SysConfigRepository
    {
        return self::getContainer()->get(SysConfigRepository::class);
    }

    private function setCatalogVersionDefault(string $version): void
    {
        $config = (new SysConfig())
            ->setPropertyName(CatalogVersionResolver::DEFAULT_VERSION_KEY)
            ->setPropertyValue($version)
            ->setIsActive(true);
        $this->em->persist($config);
        $this->em->flush();
        $this->sysConfigRepo()->invalidateCache();
    }

    private function setCatalogVersionV2Clients(string $csv): void
    {
        $config = (new SysConfig())
            ->setPropertyName(CatalogVersionResolver::V2_CLIENTS_KEY)
            ->setPropertyValue($csv)
            ->setIsActive(true);
        $this->em->persist($config);
        $this->em->flush();
        $this->sysConfigRepo()->invalidateCache();
    }

    private function v2Package(
        ?\DateTimeImmutable $activeStartAt = null,
        ?\DateTimeImmutable $activeEndAt = null,
        bool $isActive = true,
    ): CommunicationPackage {
        self::$counter++;

        $package = (new CommunicationPackage())
            ->setName("Paquete V2 {$this::$counter}")
            ->setDescription("Paquete V2 {$this::$counter}")
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP')
            ->setIsActive($isActive);

        if ($activeStartAt !== null) {
            $package->setActiveStartAt($activeStartAt);
        }
        if ($activeEndAt !== null) {
            $package->setActiveEndAt($activeEndAt);
        }

        $this->em->persist($package);

        return $package;
    }

    private function defaultContractFor(CommunicationPackage $package, float $price = 15.0, ?\DateTimeImmutable $startAt = null): CommunicationContract
    {
        $contract = (new CommunicationContract())
            ->addPackage($package)
            ->setDestinationAmount((float) $package->getDestinationAmount())
            ->setDestinationCurrency((string) $package->getDestinationCurrency())
            ->setPrice($price)
            ->setCurrency('USD');
        if ($startAt !== null) {
            $contract->setStartAt($startAt);
        }
        $this->em->persist($contract);

        return $contract;
    }

    private function bindProvider(CommunicationPackage $package, Environment $environment, string $provider = 'CSQ'): void
    {
        self::$counter++;

        $product = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId(200000 + self::$counter)
            ->setPackageType('RECHARGE')
            ->setPrice(10.0)
            ->setEnabled(true)
            ->setDescription("Producto V2 funcional {$this::$counter}")
            ->setProvider($provider)
            ->setExternalRef((string) (200000 + self::$counter));
        $this->em->persist($product);

        $binding = (new CommunicationPackageProviderProduct())
            ->setCommunicationPackage($package)
            ->setProvider($provider)
            ->setProduct($product);
        $this->em->persist($binding);
    }

    private function v1PackageProvider(): CommunicationClientPackageProvider
    {
        return self::getContainer()->get(CommunicationClientPackageProvider::class);
    }

    private function v1ItemProvider(): CommunicationClientPackageItemProvider
    {
        return self::getContainer()->get(CommunicationClientPackageItemProvider::class);
    }

    private function upcomingProvider(): UpcomingPackagesProvider
    {
        return self::getContainer()->get(UpcomingPackagesProvider::class);
    }

    // ---- 1. default v1 → sigue viendo CommunicationClientPackage (regresión) ----

    public function testDefaultV1ServesLegacyClientPackages(): void
    {
        $this->setCatalogVersionDefault('v1');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->createSellablePackage($environment, $account, 'CSQ');
        $this->authenticateAs($account);

        $result = $this->v1PackageProvider()->provide(new GetCollection(class: CommunicationClientPackage::class));

        $this->assertNotEmpty(iterator_to_array($result));
        foreach ($result as $item) {
            $this->assertInstanceOf(CommunicationClientPackage::class, $item);
        }
    }

    // ---- 2. default v2 → CommunicationPackage con precio del contrato ----

    public function testDefaultV2ServesV2PackagesWithContractPrice(): void
    {
        $this->setCatalogVersionDefault('v2');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAs($account);

        $package = $this->v2Package(activeStartAt: new \DateTimeImmutable('-1 day'));
        $this->defaultContractFor($package, 15.0);
        $this->bindProvider($package, $environment);
        $this->em->flush();

        $result = $this->v1PackageProvider()->provide(new GetCollection(class: CommunicationClientPackage::class));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CommunicationPackage::class, $result[0]);
        $this->assertSame($package->getId(), $result[0]->getId());
        $this->assertSame(15.0, $result[0]->getAmount());
    }

    // ---- 3. cliente en v2.clients con default v1 → V2 (precedencia del resolver) ----

    public function testClientInV2ClientsListOverridesTheGlobalDefault(): void
    {
        $this->setCatalogVersionDefault('v1');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAs($account);
        $this->setCatalogVersionV2Clients((string) $client->getId());

        $package = $this->v2Package(activeStartAt: new \DateTimeImmutable('-1 day'));
        $this->defaultContractFor($package, 20.0);
        $this->bindProvider($package, $environment);
        $this->em->flush();

        $result = $this->v1PackageProvider()->provide(new GetCollection(class: CommunicationClientPackage::class));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(CommunicationPackage::class, $result[0]);
    }

    // ---- 4. /upcoming ----

    public function testUpcomingReturnsFuturePackageWithContractPricePreview(): void
    {
        $this->setCatalogVersionDefault('v2');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAs($account);

        $now = new \DateTimeImmutable();
        $future = $this->v2Package(activeStartAt: $now->modify('+1 day'));
        $this->defaultContractFor($future, 12.0, $now->modify('+1 day'));
        $this->em->flush();
        $this->em->clear();

        $this->authenticateAs($this->em->getRepository(Account::class)->find($account->getId()));

        $result = $this->upcomingProvider()->provide(new GetCollection(class: CommunicationPackage::class));

        $this->assertCount(1, $result);
        $this->assertSame(12.0, $result[0]->getAmount());
    }

    public function testUpcomingOmitsPackageWhenTenantHasOwnActiveContractThatDoesNotCoverIt(): void
    {
        $this->setCatalogVersionDefault('v2');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $now = new \DateTimeImmutable();
        $otherPackage = $this->v2Package(activeStartAt: $now->modify('-1 day'));
        $ownContract = $this->defaultContractFor($otherPackage, 5.0, $now->modify('-1 day'));
        $ownContract->setTenant($account);

        $future = $this->v2Package(activeStartAt: $now->modify('+1 day'));
        $this->defaultContractFor($future, 12.0, $now->modify('+1 day'));
        $this->em->flush();
        $this->em->clear();

        $account = $this->em->getRepository(Account::class)->find($account->getId());
        $this->authenticateAs($account);

        $result = $this->upcomingProvider()->provide(new GetCollection(class: CommunicationPackage::class));

        $this->assertSame([], $result);
    }

    // ---- 5. {id} V2 ----

    public function testItemV2ReturnsTheVisiblePackageById(): void
    {
        $this->setCatalogVersionDefault('v2');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAs($account);

        $package = $this->v2Package(activeStartAt: new \DateTimeImmutable('-1 day'));
        $this->defaultContractFor($package, 18.0);
        $this->bindProvider($package, $environment);
        $this->em->flush();

        $result = $this->v1ItemProvider()->provide(new Get(class: CommunicationClientPackage::class), ['id' => (string) $package->getId()]);

        $this->assertInstanceOf(CommunicationPackage::class, $result);
        $this->assertSame($package->getId(), $result->getId());
    }

    public function testItemV2ReturnsNullForAnIdThatBelongsToTheLegacyPackageSpace(): void
    {
        $this->setCatalogVersionDefault('v2');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAs($account);

        $v1Package = $this->createSellablePackage($environment, $account, 'CSQ');

        $result = $this->v1ItemProvider()->provide(new Get(class: CommunicationClientPackage::class), ['id' => (string) $v1Package->getId()]);

        $this->assertNull($result);
    }

    // ---- 6. ventana activa (bug 0.3) ----

    public function testListingExcludesAPackageOutsideItsOwnActiveWindow(): void
    {
        $this->setCatalogVersionDefault('v2');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAs($account);

        $now = new \DateTimeImmutable();
        $active = $this->v2Package(activeStartAt: $now->modify('-1 day'));
        $sharedContract = $this->defaultContractFor($active, 10.0);
        $this->bindProvider($active, $environment);

        // Reutiliza el MISMO contrato default (misma tupla) para un paquete
        // de promoción futura — reproduce el bug 0.3: el contrato ya está
        // vigente aunque el paquete no lo esté.
        $future = $this->v2Package(activeStartAt: $now->modify('+1 day'));
        $sharedContract->addPackage($future);
        $this->bindProvider($future, $environment);
        $this->em->flush();

        $result = $this->v1PackageProvider()->provide(new GetCollection(class: CommunicationClientPackage::class));

        $ids = array_map(static fn (CommunicationPackage $p) => $p->getId(), iterator_to_array($result));
        $this->assertContains($active->getId(), $ids);
        $this->assertNotContains($future->getId(), $ids);
    }

    // ---- 7. contrato propio del tenant no oculta promociones nuevas (bug reportado 2026-08-19) ----

    /**
     * Regresión del bug real encontrado en staging: un tenant con contrato
     * propio sobre el catálogo regular nunca veía promociones nuevas
     * (createV2() solo genera contratos "por defecto", tenant IS NULL, y
     * PackageCatalogResolver::catalogFor() ignora esa rama por completo en
     * cuanto el tenant tiene AL MENOS un contrato propio). Fix:
     * CommunicationContractService::linkTenantContractsToPromotionPackages()
     * vincula el paquete de promoción al MISMO contrato que el tenant ya
     * tiene para su equivalente regular.
     */
    public function testTenantWithOwnContractStillSeesANewPromotionalPackage(): void
    {
        $this->setCatalogVersionDefault('v2');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $this->authenticateAs($account);

        // Paquete regular ya existente, con contrato PROPIO del tenant (no
        // "por defecto") — reproduce exactamente el caso de Comremit en
        // staging.
        $regularPackage = $this->v2Package(activeStartAt: new \DateTimeImmutable('-30 days'));
        $ownContract = $this->defaultContractFor($regularPackage, 10.0, new \DateTimeImmutable('-30 days'));
        $ownContract->setTenant($account);
        $this->bindProvider($regularPackage, $environment);
        $this->em->flush();

        /** @var CommunicationPromotionService $promotionService */
        $promotionService = self::getContainer()->get(CommunicationPromotionService::class);
        $dto = new CreatePromotionV2Dto(
            name: 'Promo regresión',
            description: 'Promo regresión',
            packageNameTemplate: 'Promo {monto} CUP',
            packageDescriptionTemplate: 'Promo {monto} CUP',
            startAt: (new \DateTimeImmutable('-1 day'))->format('c'),
            endAt: (new \DateTimeImmutable('+5 days'))->format('c'),
            environmentId: $environment->getId(),
            destinationCurrency: 'CUP',
            amountFrom: (float) $regularPackage->getDestinationAmount(),
            amountTo: (float) $regularPackage->getDestinationAmount(),
            amountStep: 1.0,
            priceFrom: 25.0,
            priceTo: 25.0,
            priceCurrency: 'USD',
        );

        $result = $promotionService->createV2($dto);
        $promoPackage = $result->packages[0];
        $this->bindProvider($promoPackage, $environment);
        $this->em->flush();
        $this->em->clear();

        $this->assertSame(1, $result->tenantContractsLinked);

        $account = $this->em->getRepository(Account::class)->find($account->getId());
        $this->authenticateAs($account);

        $listed = $this->v1PackageProvider()->provide(new GetCollection(class: CommunicationClientPackage::class));
        $ids = array_map(static fn (CommunicationPackage $p) => $p->getId(), iterator_to_array($listed));

        $this->assertContains($regularPackage->getId(), $ids);
        $this->assertContains($promoPackage->getId(), $ids, 'La promoción debe verse aunque el tenant ya tenga contrato propio.');
    }

    // ---- 8. operación de beneficios (multiplicación/adición/igualdad) ----

    public function testCreateV2AppliesMultiplyOperationForACreditsCurrencyBenefit(): void
    {
        $environment = $this->createEnvironment();

        $dto = new CreatePromotionV2Dto(
            name: 'Sextuplica',
            description: 'Sextuplica',
            packageNameTemplate: 'Promo {monto} CUP',
            packageDescriptionTemplate: 'Promo {monto} CUP',
            startAt: (new \DateTimeImmutable('-1 day'))->format('c'),
            endAt: (new \DateTimeImmutable('+5 days'))->format('c'),
            environmentId: $environment->getId(),
            destinationCurrency: 'CUP',
            amountFrom: 600.0,
            amountTo: 600.0,
            amountStep: 1.0,
            priceFrom: 25.0,
            priceTo: 25.0,
            priceCurrency: 'USD',
            benefits: [[
                'type' => 'CREDITS',
                'unit' => 'CUP',
                'unit_type' => 'CURRENCY',
                'operation' => 'MULTIPLY',
                'value' => 6,
            ]],
        );

        /** @var CommunicationPromotionService $promotionService */
        $promotionService = self::getContainer()->get(CommunicationPromotionService::class);
        $result = $promotionService->createV2($dto);

        $benefit = $result->packages[0]->getBenefits()[0];
        $this->assertSame(600, $benefit['amount']['base']);
        $this->assertSame(3000, $benefit['amount']['promotion_bonus']);
        $this->assertSame(3600, $benefit['amount']['total_excluding_tax']);
    }

    public function testCreateV2AppliesAddOperationUsingTheRegularEquivalentPackagesBenefit(): void
    {
        $environment = $this->createEnvironment();

        $regularPackage = $this->v2Package(activeStartAt: new \DateTimeImmutable('-30 days'));
        $regularPackage->setDestinationAmount(600.0)->setDestinationCurrency('CUP');
        $regularPackage->setBenefits([[
            'type' => 'DATA', 'unit' => 'GB', 'unit_type' => 'DATA',
            'amount' => ['base' => 5, 'promotion_bonus' => 0, 'total_excluding_tax' => 5, 'total_including_tax' => 5],
        ]]);
        $this->em->flush();

        $dto = new CreatePromotionV2Dto(
            name: 'Bonifica 20GB',
            description: 'Bonifica 20GB',
            packageNameTemplate: 'Promo {monto} CUP',
            packageDescriptionTemplate: 'Promo {monto} CUP',
            startAt: (new \DateTimeImmutable('-1 day'))->format('c'),
            endAt: (new \DateTimeImmutable('+5 days'))->format('c'),
            environmentId: $environment->getId(),
            destinationCurrency: 'CUP',
            amountFrom: 600.0,
            amountTo: 600.0,
            amountStep: 1.0,
            priceFrom: 25.0,
            priceTo: 25.0,
            priceCurrency: 'USD',
            benefits: [[
                'type' => 'DATA',
                'unit' => 'GB',
                'unit_type' => 'DATA',
                'operation' => 'ADD',
                'value' => 20,
            ]],
        );

        /** @var CommunicationPromotionService $promotionService */
        $promotionService = self::getContainer()->get(CommunicationPromotionService::class);
        $result = $promotionService->createV2($dto);

        $benefit = $result->packages[0]->getBenefits()[0];
        $this->assertSame(5, $benefit['amount']['base']);
        $this->assertSame(20, $benefit['amount']['promotion_bonus']);
        $this->assertSame(25, $benefit['amount']['total_excluding_tax']);
    }

    // ---- Shape autoritativo: serializer real ----

    public function testRealSerializerProducesTheSameKeysForBothCatalogVersions(): void
    {
        $serializer = self::getContainer()->get('api_platform.serializer');

        $v1 = (new CommunicationClientPackage())
            ->setTenant($this->createAccount($this->createClient(), $this->createEnvironment()))
            ->setName('P')->setDescription('P')->setAmount(1.0)->setCurrency('USD')
            ->setActiveEndAt(new \DateTimeImmutable('+1 year'));
        $this->em->persist($v1);

        $v2 = $this->v2Package(activeStartAt: new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $v1Data = $serializer->normalize($v1, 'json', ['groups' => ['comPackage:read']]);
        $v2Data = $serializer->normalize($v2, 'json', ['groups' => ['comPackage:read']]);

        $missingInV2 = array_diff(array_keys($v1Data), array_keys($v2Data));
        $this->assertSame([], $missingInV2, 'El serializer real confirma que V2 cubre todas las claves de V1: ' . implode(', ', $missingInV2));
    }
}
