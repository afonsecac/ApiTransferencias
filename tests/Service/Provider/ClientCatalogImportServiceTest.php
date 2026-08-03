<?php

namespace App\Tests\Service\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Repository\AccountRepository;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationPricePackageRepository;
use App\Repository\CommunicationProductRepository;
use App\Service\Provider\ClientCatalogImportService;
use App\Service\Provider\CommunicationCatalogSyncService;
use App\Service\Provider\ProductPriceResolver;
use App\Service\Provider\ResolvedProductPrice;
use App\Service\Etecsa\SyncResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Provider\ClientCatalogImportService
 *
 * La resolución de precio/conversión en sí la cubre
 * ProductPriceResolverTest — aquí se mockea para probar solo el
 * enrutado/creación por (producto, cuenta).
 */
class ClientCatalogImportServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationCatalogSyncService&MockObject $catalogSyncService;
    private ProductPriceResolver&MockObject $priceResolver;
    private ClientCatalogImportService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->catalogSyncService = $this->createMock(CommunicationCatalogSyncService::class);
        $this->priceResolver = $this->createMock(ProductPriceResolver::class);

        $this->service = new ClientCatalogImportService(
            $this->em,
            $this->catalogSyncService,
            $this->priceResolver,
            new NullLogger(),
        );
    }

    private function accountWithId(int $id, Client $client, Environment $environment): Account&MockObject
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($id);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);

        return $account;
    }

    /**
     * Por defecto no existe ningún CommunicationClientPackage todavía (así
     * createClientPackageIfMissing crea uno nuevo) — pasar $existing para
     * simular que ya estaba asignado.
     */
    private function clientPackageRepo(?CommunicationClientPackage $existing = null): CommunicationClientPackageRepository&MockObject
    {
        $repo = $this->createMock(CommunicationClientPackageRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        return $repo;
    }

    private function productWithWholesalePrice(int $id, float $price, string $priceCurrency): CommunicationProduct&MockObject
    {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getId')->willReturn($id);
        $product->method('getPrice')->willReturn($price);
        $product->method('getPriceCurrency')->willReturn($priceCurrency);
        $product->method('getDescription')->willReturn('Producto de prueba');
        $product->method('getExternalRef')->willReturn('ext-1');

        return $product;
    }

    /**
     * $isMobileOrInternetService por defecto true: estos productos de
     * prueba aíslan la dimensión PIN_PURCHASE vs RECHARGE del packageType —
     * la dimensión móvil/Internet se prueba aparte (ver
     * testSkipsNonMobileInternetProductWhenRoutingSaleTypeIsRecharge y
     * testImportsNonMobileInternetProductWhenRoutingSaleTypeIsSale).
     */
    private function productWithPackageType(int $id, string $packageType, bool $isMobileOrInternetService = true): CommunicationProduct&MockObject
    {
        $product = $this->productWithWholesalePrice($id, 4.5, 'USD');
        $product->method('getPackageType')->willReturn($packageType);
        $product->method('isMobileOrInternetService')->willReturn($isMobileOrInternetService);

        return $product;
    }

    public function testDoesNothingWhenProviderIsEtecsa(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setClient(new Client());
        $routing->setProvider(CommunicationProviderEnum::ETECSA->value);

        $this->catalogSyncService->expects($this->never())->method('syncProducts');
        $this->em->expects($this->never())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testDoesNothingWhenProviderUnknown(): void
    {
        $routing = new ClientProviderRouting();
        $routing->setClient(new Client());
        $routing->setProvider('ALGO_NO_REGISTRADO');

        $this->catalogSyncService->expects($this->never())->method('syncProducts');

        $this->service->importForRouting($routing);
    }

    public function testCreatesOnePricePackagePerActiveAccountAndProduct(): void
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->expects($this->once())
            ->method('syncProducts')
            ->with(CommunicationProviderEnum::DTONE, $environment)
            ->willReturn(new SyncResult(1, 0, 0));

        $product = $this->productWithWholesalePrice(1, 4.5, 'USD');
        $account1 = $this->accountWithId(101, $client, $environment);
        $account2 = $this->accountWithId(102, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);

        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account1, $account2]);

        $pricePackageRepo = $this->createMock(CommunicationPricePackageRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationPricePackage::class, $pricePackageRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo()],
        ]);

        $this->priceResolver->method('resolve')
            ->with($product, 'USD', $this->logicalOr(101, 102))
            ->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, null));

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);

        // Por cada (producto, cuenta): un CommunicationPricePackage y el
        // CommunicationClientPackage que lo hace comprable de verdad.
        $pricePackages = array_filter($persisted, fn ($e) => $e instanceof CommunicationPricePackage);
        $clientPackages = array_filter($persisted, fn ($e) => $e instanceof CommunicationClientPackage);
        $this->assertCount(4, $persisted);
        $this->assertCount(2, $pricePackages);
        $this->assertCount(2, $clientPackages);
        foreach ($pricePackages as $pricePackage) {
            $this->assertSame(4.5, $pricePackage->getAmount());
            $this->assertSame('USD', $pricePackage->getCurrency());
            $this->assertNull($pricePackage->getKnowMore());
            $this->assertTrue($pricePackage->isAutoManaged());
        }
        foreach ($clientPackages as $clientPackage) {
            $this->assertSame(4.5, $clientPackage->getAmount());
            $this->assertSame('USD', $clientPackage->getCurrency());
            $this->assertNotNull($clientPackage->getActiveEndAt());
        }
    }

    public function testStoresConversionRateAndDateFromResolver(): void
    {
        $client = (new Client())->setCurrency('EUR');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithWholesalePrice(1, 4.5, 'USD');
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);
        $pricePackageRepo = $this->createMock(CommunicationPricePackageRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationPricePackage::class, $pricePackageRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo()],
        ]);

        $rateDate = new \DateTimeImmutable('2026-07-31');
        $this->priceResolver->method('resolve')
            ->willReturn(new ResolvedProductPrice(4.0, 'EUR', 0.89, $rateDate, null));

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $this->service->importForRouting($routing);

        $pricePackages = array_values(array_filter($persisted, fn ($e) => $e instanceof CommunicationPricePackage));
        $this->assertCount(1, $pricePackages);
        $this->assertSame(4.0, $pricePackages[0]->getAmount());
        $this->assertSame('EUR', $pricePackages[0]->getCurrency());
        $this->assertSame(0.89, $pricePackages[0]->getConversionRate());
        $this->assertSame($rateDate, $pricePackages[0]->getConversionRateDate());
        // El costo mayorista original (price/priceCurrency) se conserva sin tocar.
        $this->assertSame(4.5, $pricePackages[0]->getPrice());
        $this->assertSame('USD', $pricePackages[0]->getPriceCurrency());
    }

    public function testAppliesPendingNoteWhenResolverFlagsIt(): void
    {
        $client = (new Client())->setCurrency('EUR');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithWholesalePrice(1, 4.5, 'USD');
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);
        $pricePackageRepo = $this->createMock(CommunicationPricePackageRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationPricePackage::class, $pricePackageRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo()],
        ]);

        $this->priceResolver->method('resolve')
            ->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, '[Pendiente conversión de moneda] ...'));

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $this->service->importForRouting($routing);

        $pricePackages = array_values(array_filter($persisted, fn ($e) => $e instanceof CommunicationPricePackage));
        $this->assertCount(1, $pricePackages);
        $this->assertNotNull($pricePackages[0]->getKnowMore());
        $this->assertStringContainsString('Pendiente conversión de moneda', $pricePackages[0]->getKnowMore());
    }

    public function testSkipsPricePackageThatAlreadyExists(): void
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithWholesalePrice(1, 4.5, 'USD');
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);

        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);

        $pricePackageRepo = $this->createMock(CommunicationPricePackageRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn($this->createMock(CommunicationPricePackage::class));

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationPricePackage::class, $pricePackageRepo],
            // El CommunicationClientPackage TAMBIÉN ya existe — "todo ya
            // estaba" es el único caso donde de verdad no debe pasar nada.
            [CommunicationClientPackage::class, $this->clientPackageRepo($this->createMock(CommunicationClientPackage::class))],
        ]);

        $this->priceResolver->expects($this->never())->method('resolve');
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->service->importForRouting($routing);
    }

    /**
     * Escenario real de "backfill": el CommunicationPricePackage ya existía
     * (de una importación anterior a que este fix creara también el
     * CommunicationClientPackage) — debe rellenar solo lo que falta, sin
     * tocar ni duplicar el precio. Esto es exactamente lo que hizo falta
     * para los 143 paquetes de Comremit importados el 2026-08-02, antes de
     * que existiera createClientPackageIfMissing().
     */
    public function testBackfillsClientPackageWhenOnlyThePricePackageAlreadyExisted(): void
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithWholesalePrice(1, 4.5, 'USD');
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);

        $existingPricePackage = $this->createMock(CommunicationPricePackage::class);
        $existingPricePackage->method('getAmount')->willReturn(9.85);
        $existingPricePackage->method('getCurrency')->willReturn('USD');
        $existingPricePackage->method('getEnvironment')->willReturn($environment);
        $existingPricePackage->method('getProduct')->willReturn($product);
        $pricePackageRepo = $this->createMock(CommunicationPricePackageRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn($existingPricePackage);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationPricePackage::class, $pricePackageRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo()],
        ]);

        $this->priceResolver->expects($this->never())->method('resolve');

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);

        $this->assertCount(1, $persisted);
        $this->assertInstanceOf(CommunicationClientPackage::class, $persisted[0]);
        $this->assertSame(9.85, $persisted[0]->getAmount());
        $this->assertSame('USD', $persisted[0]->getCurrency());
        $this->assertNotNull($persisted[0]->getActiveEndAt());
    }

    public function testSyncFailureIsLoggedAndDoesNotThrow(): void
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willThrowException(new \RuntimeException('DTOne inalcanzable'));

        $this->em->expects($this->never())->method('flush');

        // No debe propagar la excepción.
        $this->service->importForRouting($routing);
        $this->addToAssertionCount(1);
    }

    // ---- filtro por routing.saleType (ver matchesSaleType) ----

    private function setUpSingleProductImport(?string $saleType, string $packageType, bool $isMobileOrInternetService = true): array
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);
        $routing->setSaleType($saleType);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithPackageType(1, $packageType, $isMobileOrInternetService);
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);
        $pricePackageRepo = $this->createMock(CommunicationPricePackageRepository::class);
        $pricePackageRepo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationPricePackage::class, $pricePackageRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo()],
        ]);

        $this->priceResolver->method('resolve')->willReturn(new ResolvedProductPrice(4.5, 'USD', null, null, null));

        return [$routing];
    }

    public function testSkipsPinPurchaseProductWhenRoutingSaleTypeIsRecharge(): void
    {
        [$routing] = $this->setUpSingleProductImport('recharge', 'FIXED_VALUE_PIN_PURCHASE');

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testImportsRechargeProductWhenRoutingSaleTypeIsRecharge(): void
    {
        [$routing] = $this->setUpSingleProductImport('recharge', 'FIXED_VALUE_RECHARGE');

        // El precio y el CommunicationClientPackage que lo hace comprable.
        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testSkipsRechargeProductWhenRoutingSaleTypeIsSale(): void
    {
        [$routing] = $this->setUpSingleProductImport('sale', 'FIXED_VALUE_RECHARGE');

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testImportsPinPurchaseProductWhenRoutingSaleTypeIsSale(): void
    {
        [$routing] = $this->setUpSingleProductImport('sale', 'FIXED_VALUE_PIN_PURCHASE');

        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testImportsBothProductTypesWhenRoutingSaleTypeIsNull(): void
    {
        [$routing] = $this->setUpSingleProductImport(null, 'FIXED_VALUE_PIN_PURCHASE');

        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testSkipsNonMobileInternetProductWhenRoutingSaleTypeIsRecharge(): void
    {
        // p.ej. un gift card de comida o una SIM/equipo físico: DTOne lo
        // entrega igual como FIXED_VALUE_RECHARGE, pero no es servicio móvil
        // ni Internet — no debe colarse en un enrutado de 'recharge'.
        [$routing] = $this->setUpSingleProductImport('recharge', 'FIXED_VALUE_RECHARGE', isMobileOrInternetService: false);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testImportsNonMobileInternetProductWhenRoutingSaleTypeIsSale(): void
    {
        [$routing] = $this->setUpSingleProductImport('sale', 'FIXED_VALUE_RECHARGE', isMobileOrInternetService: false);

        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);
    }
}
