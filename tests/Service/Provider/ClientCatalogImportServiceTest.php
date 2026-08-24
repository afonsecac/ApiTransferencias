<?php

namespace App\Tests\Service\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Repository\AccountRepository;
use App\Repository\CommunicationClientPackageRepository;
use App\Repository\CommunicationProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\Pricing\PackageMaterializationService;
use App\Service\Provider\ClientCatalogImportService;
use App\Service\Provider\CommunicationCatalogSyncService;
use App\Service\Provider\ProductSaleTypeMatcher;
use App\Service\Etecsa\SyncResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Service\Provider\ClientCatalogImportService
 *
 * Desde el rediseño de precios/paquetes, este servicio ya NO crea ningún
 * CommunicationPricePackage (contrato): asegura un paquete REFERENCIA
 * (CommunicationClientPackage, tenant NULL) por producto y delega la
 * materialización por cuenta en PackageMaterializationService (mockeado
 * aquí — su propia lógica de clonado la cubre
 * PackageMaterializationServiceTest). Este test cubre solo el
 * enrutado/creación por (producto, cuenta) y el filtro de saleType.
 */
class ClientCatalogImportServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommunicationCatalogSyncService&MockObject $catalogSyncService;
    private PackageMaterializationService&MockObject $materializationService;
    private SysConfigRepository&MockObject $gatingSysConfigRepo;
    private CatalogVersionResolver $catalogVersion;
    private ClientCatalogImportService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->catalogSyncService = $this->createMock(CommunicationCatalogSyncService::class);
        $this->materializationService = $this->createMock(PackageMaterializationService::class);
        // CatalogVersionResolver es `final` — instancia real sobre un
        // SysConfigRepository mockeado propio (mismo patrón que
        // PackageCatalogResolverTest/ContractGatingScopeResolverTest). Sin
        // stub por defecto: un mock sin configurar ya devuelve null →
        // isV2() da false (todas las cuentas quedan V1) salvo que un test
        // llame explícitamente a markAccountsAsV2().
        $this->gatingSysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->catalogVersion = new CatalogVersionResolver($this->gatingSysConfigRepo);

        $this->service = new ClientCatalogImportService(
            $this->em,
            $this->catalogSyncService,
            $this->materializationService,
            new ProductSaleTypeMatcher(),
            $this->catalogVersion,
            new NullLogger(),
        );
    }

    private function markAccountsAsV2(): void
    {
        $this->gatingSysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === CatalogVersionResolver::DEFAULT_VERSION_KEY ? 'v2' : null);
    }

    private function accountWithId(int $id, Client $client, Environment $environment): Account&MockObject
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($id);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);

        return $account;
    }

    private function productWithId(int $id): CommunicationProduct&MockObject
    {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getId')->willReturn($id);
        $product->method('getDescription')->willReturn('Producto de prueba');
        $product->method('getExternalRef')->willReturn('ext-1');
        $product->method('getBenefits')->willReturn([]);
        $product->method('getService')->willReturn([]);

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
        $product = $this->productWithId($id);
        $product->method('getPackageType')->willReturn($packageType);
        $product->method('isMobileOrInternetService')->willReturn($isMobileOrInternetService);

        return $product;
    }

    /**
     * @param CommunicationClientPackage|null $existingReference fila con
     *   `product`+`tenant IS NULL` que ya existía
     * @param array<int, CommunicationClientPackage> $existingMaterializedByAccountId
     *   filas con `referencePackage`+`tenant` que ya existían, indexadas
     *   por id de cuenta
     */
    private function clientPackageRepo(
        ?CommunicationClientPackage $existingReference,
        array $existingMaterializedByAccountId = [],
    ): CommunicationClientPackageRepository&MockObject {
        $repo = $this->createMock(CommunicationClientPackageRepository::class);
        $repo->method('findOneBy')->willReturnCallback(
            function (array $criteria) use ($existingReference, $existingMaterializedByAccountId) {
                if (array_key_exists('product', $criteria)) {
                    return $existingReference;
                }
                if (array_key_exists('referencePackage', $criteria)) {
                    $accountId = $criteria['tenant']->getId();

                    return $existingMaterializedByAccountId[$accountId] ?? null;
                }

                return null;
            }
        );

        return $repo;
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

    public function testCreatesOneReferencePackagePerProductAndMaterializesPerActiveAccount(): void
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

        $product = $this->productWithId(1);
        $account1 = $this->accountWithId(101, $client, $environment);
        $account2 = $this->accountWithId(102, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account1, $account2]);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo(null)],
        ]);

        $materializeCalls = [];
        $this->materializationService->expects($this->exactly(2))
            ->method('materializeForTenant')
            ->willReturnCallback(function ($reference, $account) use (&$materializeCalls) {
                $materializeCalls[] = [$reference, $account];

                return new CommunicationClientPackage();
            });

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);

        // Solo la referencia se persiste aquí — la materialización por
        // cuenta la hace PackageMaterializationService (mockeado).
        $this->assertCount(1, $persisted);
        $this->assertInstanceOf(CommunicationClientPackage::class, $persisted[0]);
        $this->assertNull($persisted[0]->getTenant());
        $this->assertSame($product, $persisted[0]->getProduct());
        $this->assertNotNull($persisted[0]->getActiveEndAt());

        $this->assertCount(2, $materializeCalls);
        $this->assertSame($persisted[0], $materializeCalls[0][0]);
        $this->assertSame($account1, $materializeCalls[0][1]);
        $this->assertSame($persisted[0], $materializeCalls[1][0]);
        $this->assertSame($account2, $materializeCalls[1][1]);
    }

    // ---- Fase 3 de la deprecación de V1: cuentas ya V2 no materializan V1 ----

    public function testSkipsReferenceAndMaterializationWhenAllAccountsAreAlreadyV2(): void
    {
        $this->markAccountsAsV2();

        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        // syncProducts() SIEMPRE corre — V2 también depende de que
        // CommunicationProduct exista (MAX+margen, coverage/bindings).
        $this->catalogSyncService->expects($this->once())
            ->method('syncProducts')
            ->with(CommunicationProviderEnum::DTONE, $environment)
            ->willReturn(new SyncResult(1, 0, 0));

        $product = $this->productWithId(1);
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
        ]);

        $this->em->expects($this->never())->method('persist');
        $this->materializationService->expects($this->never())->method('materializeForTenant');
        $this->em->expects($this->never())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testOnlyMaterializesForTheStillV1AccountsWhenMixedWithV2(): void
    {
        // OJO: NO llamar a markAccountsAsV2() acá — este test necesita que
        // el default GLOBAL se mantenga en "v1" (sin stub = null, isV2()
        // cae en false) y que SOLO el cliente 200 sea V2 vía la lista de
        // pilotos. PHPUnit usa el PRIMER stub sin restricciones que
        // matchea una invocación, no el último — dos willReturnCallback()
        // seguidos sobre el mismo mock pisarían el segundo en silencio.
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult(1, 0, 0));

        $product = $this->productWithId(1);
        // El cliente 200 está en la lista de pilotos V2 — el resto sigue V1.
        $v2Client = $this->createMock(Client::class);
        $v2Client->method('getId')->willReturn(200);
        $v1Account = $this->accountWithId(101, $client, $environment);
        $v2Account = $this->accountWithId(102, $v2Client, $environment);

        $this->gatingSysConfigRepo->method('findCachedValue')->willReturnCallback(
            fn (string $key) => match ($key) {
                CatalogVersionResolver::V2_CLIENTS_KEY => '200',
                default => null,
            }
        );

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$v1Account, $v2Account]);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo(null)],
        ]);

        $materializedFor = [];
        $this->materializationService->method('materializeForTenant')
            ->willReturnCallback(function ($reference, $account) use (&$materializedFor) {
                $materializedFor[] = $account;

                return new CommunicationClientPackage();
            });
        $this->em->method('persist')->willReturnCallback(fn () => null);
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);

        $this->assertSame([$v1Account], $materializedFor);
    }

    public function testSkipsWhenReferenceAndMaterializedPackageAlreadyExist(): void
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithId(1);
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);

        $existingReference = $this->createMock(CommunicationClientPackage::class);
        $existingMaterialized = $this->createMock(CommunicationClientPackage::class);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo($existingReference, [101 => $existingMaterialized])],
        ]);

        $this->materializationService->expects($this->never())->method('materializeForTenant');
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->service->importForRouting($routing);
    }

    /**
     * Escenario de "backfill": la referencia ya existía (de una importación
     * anterior), pero a esta cuenta todavía no se le había materializado su
     * copia — debe rellenar solo lo que falta, sin duplicar la referencia.
     */
    public function testMaterializesWhenReferenceAlreadyExistedButAccountDidNot(): void
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithId(1);
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);

        $existingReference = $this->createMock(CommunicationClientPackage::class);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo($existingReference)],
        ]);

        $this->materializationService->expects($this->once())
            ->method('materializeForTenant')
            ->with($existingReference, $account)
            ->willReturn(new CommunicationClientPackage());

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);
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

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo(null)],
        ]);

        $this->materializationService->method('materializeForTenant')->willReturn(new CommunicationClientPackage());

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

        // Solo la referencia se persiste desde este servicio; la
        // materialización la hace PackageMaterializationService (mockeado).
        $this->em->expects($this->once())->method('persist');
        $this->materializationService->expects($this->once())->method('materializeForTenant');
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

        $this->em->expects($this->once())->method('persist');
        $this->materializationService->expects($this->once())->method('materializeForTenant');
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);
    }

    public function testImportsBothProductTypesWhenRoutingSaleTypeIsNull(): void
    {
        [$routing] = $this->setUpSingleProductImport(null, 'FIXED_VALUE_PIN_PURCHASE');

        $this->em->expects($this->once())->method('persist');
        $this->materializationService->expects($this->once())->method('materializeForTenant');
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

        $this->em->expects($this->once())->method('persist');
        $this->materializationService->expects($this->once())->method('materializeForTenant');
        $this->em->expects($this->once())->method('flush');

        $this->service->importForRouting($routing);
    }

    // ---- deriveTags() — Nauta WiFi vs. Nauta PLUS vs. Nauta Hogar ----

    private function productWithServiceAndBenefits(int $id, array $service, array $benefits): CommunicationProduct&MockObject
    {
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getId')->willReturn($id);
        $product->method('getDescription')->willReturn('Producto de prueba');
        $product->method('getExternalRef')->willReturn('ext-1');
        $product->method('getService')->willReturn($service);
        $product->method('getBenefits')->willReturn($benefits);

        return $product;
    }

    /**
     * @param array<string, mixed> $service
     * @param array<int, array<string, mixed>> $benefits
     */
    private function importSingleProductAndCaptureTags(array $service, array $benefits): array
    {
        $client = (new Client())->setCurrency('USD');
        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn(10);

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setProvider(CommunicationProviderEnum::DTONE->value);

        $this->catalogSyncService->method('syncProducts')->willReturn(new SyncResult());

        $product = $this->productWithServiceAndBenefits(1, $service, $benefits);
        $account = $this->accountWithId(101, $client, $environment);

        $productRepo = $this->createMock(CommunicationProductRepository::class);
        $productRepo->method('findBy')->willReturn([$product]);
        $accountRepo = $this->createMock(AccountRepository::class);
        $accountRepo->method('findBy')->willReturn([$account]);

        $this->em->method('getRepository')->willReturnMap([
            [CommunicationProduct::class, $productRepo],
            [Account::class, $accountRepo],
            [CommunicationClientPackage::class, $this->clientPackageRepo(null)],
        ]);

        $this->materializationService->method('materializeForTenant')->willReturn(new CommunicationClientPackage());

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });

        $this->service->importForRouting($routing);

        return $persisted;
    }

    public function testDerivesInternetTagForNautaWifiRecharge(): void
    {
        $persisted = $this->importSingleProductAndCaptureTags(
            ['name' => 'Utilities', 'subservice' => ['name' => 'Internet']],
            [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'amount' => ['base' => 250]]],
        );

        $this->assertSame(['INTERNET'], $persisted[0]->getTags());
    }

    /**
     * Nauta PLUS comparte subservice=Internet con Nauta WIFI Recharge, pero
     * su benefit es de datos ilimitados (type=DATA, amount.base=-1) — debe
     * quedar distinguible con un tag extra, no confundirse con la recarga
     * simple (ver conversación sobre cómo diferenciar ambos productos).
     */
    public function testDerivesInternetAndUnlimitedTagsForNautaPlus(): void
    {
        $persisted = $this->importSingleProductAndCaptureTags(
            ['name' => 'Utilities', 'subservice' => ['name' => 'Internet']],
            [['type' => 'DATA', 'unit' => 'MB', 'unit_type' => 'DATA', 'amount' => ['base' => -1]]],
        );

        $this->assertSame(['INTERNET', 'UNLIMITED'], $persisted[0]->getTags());
    }

    public function testDerivesLandlineTagForNautaHogar(): void
    {
        $persisted = $this->importSingleProductAndCaptureTags(
            ['name' => 'Utilities', 'subservice' => ['name' => 'Landline']],
            [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'amount' => ['base' => 480]]],
        );

        $this->assertSame(['LANDLINE'], $persisted[0]->getTags());
    }

    public function testDerivesNoTagForUnrecognizedSubservice(): void
    {
        $persisted = $this->importSingleProductAndCaptureTags(
            ['name' => 'Utilities', 'subservice' => null],
            [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'amount' => ['base' => 1000]]],
        );

        $this->assertSame([], $persisted[0]->getTags());
    }
}
