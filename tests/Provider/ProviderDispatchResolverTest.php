<?php

namespace App\Tests\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderDispatchResolver;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Repository\CommunicationProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProductSaleTypeMatcher;
use App\Service\Provider\ProviderAvailabilityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\ProviderDispatchResolver
 *
 * A diferencia de ProviderResolver (admisión: un solo proveedor resuelto
 * por especificidad), este resuelve DESPACHO: filtra las filas de
 * ClientProviderRouting aplicables a esta venta (entorno/tipo de
 * venta/categoría), las ordena por especificidad y recorre proveedor +
 * fallbackProvider de cada una hasta encontrar uno disponible con un
 * producto que cubra la tupla — el camino "con promoción" es Fase 5, no
 * cubierto aquí.
 */
class ProviderDispatchResolverTest extends TestCase
{
    private ClientProviderRoutingRepository&MockObject $routingRepo;
    private CommunicationProductRepository&MockObject $productRepository;
    private CommunicationPackageProviderProductRepository&MockObject $packageBindingRepo;
    private ProviderAvailabilityService&MockObject $availabilityService;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private ProviderDispatchResolver $resolver;

    protected function setUp(): void
    {
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->productRepository = $this->createMock(CommunicationProductRepository::class);
        $this->packageBindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);

        // Sin vínculo explícito por defecto — los tests existentes ejercitan
        // el camino de matching automático por tupla, sin cambios.
        $this->packageBindingRepo->method('findForPackageAndProvider')->willReturn(null);

        $this->resolver = new ProviderDispatchResolver(
            $this->routingRepo,
            $this->productRepository,
            $this->packageBindingRepo,
            $this->availabilityService,
            new ProductSaleTypeMatcher(),
            $this->sysConfigRepo,
        );

        // Kill switch en '1' por defecto salvo que el test lo apague.
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(function (string $key) {
                return $key === ProviderResolver::ROUTING_ENABLED_KEY ? '1' : null;
            });
    }

    private function account(int $clientId, ?int $environmentId = 10): Account&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn($clientId);

        $environment = $this->createMock(Environment::class);
        $environment->method('getId')->willReturn($environmentId);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);

        return $account;
    }

    /**
     * Fila escalar tal como la devuelve
     * ClientProviderRoutingRepository::findActiveRouteScopesForClient() —
     * null en cualquier dimensión = comodín, aplica siempre.
     *
     * @return array{id:int, provider:?string, fallbackProvider:?string,
     *   environmentId:?int, saleType:?string, serviceName:?string,
     *   subserviceName:?string, priority:int}
     */
    private function routeScope(
        string $provider,
        ?string $fallbackProvider = null,
        ?int $environmentId = null,
        ?string $saleType = null,
        ?string $serviceName = null,
        ?string $subserviceName = null,
        int $priority = 100,
        int $id = 1,
    ): array {
        return [
            'id' => $id,
            'provider' => $provider,
            'fallbackProvider' => $fallbackProvider,
            'environmentId' => $environmentId,
            'saleType' => $saleType,
            'serviceName' => $serviceName,
            'subserviceName' => $subserviceName,
            'priority' => $priority,
        ];
    }

    private function package(): CommunicationPackage
    {
        return (new CommunicationPackage())
            ->setName('Paquete')
            ->setDescription('Paquete')
            ->setDestinationAmount(500.0)
            ->setDestinationCurrency('CUP');
    }

    private function packageWithService(string $name, ?string $subserviceName = null): CommunicationPackage
    {
        $shape = ['name' => $name];
        if ($subserviceName !== null) {
            $shape['subservice'] = ['name' => $subserviceName];
        }

        return $this->package()->setService($shape);
    }

    /**
     * Tramo generado por una promoción — a diferencia de package(), NUNCA
     * debe caer al matching automático por tupla.
     */
    private function promotionalPackage(): CommunicationPackage
    {
        return $this->package()->setPromotion($this->createMock(CommunicationPromotions::class));
    }

    public function testSelectsTheFirstDispatchableProviderInPriorityOrder(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-1');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', priority: 0, id: 1), $this->routeScope('DTONE', priority: 10, id: 2)]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')
            ->willReturnCallback(fn ($amount, $currency, $env, $provider) => $provider === 'CSQ' ? [$product] : []);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::CSQ, $selected->provider);
        $this->assertSame('ref-1', $selected->externalRef);
    }

    public function testExplicitBindingWinsOverAutomaticTupleMatching(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $bound = $this->createMock(CommunicationProduct::class);
        $bound->method('getExternalRef')->willReturn('ref-bound');
        $bound->method('isEnabled')->willReturn(true);
        $bound->method('getEnvironment')->willReturn(null);
        $binding = $this->createMock(CommunicationPackageProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->packageBindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->packageBindingRepo->method('findForPackageAndProvider')
            ->willReturnCallback(fn ($pkg, $provider) => $provider === 'CSQ' ? $binding : null);
        $this->resolver = new ProviderDispatchResolver(
            $this->routingRepo,
            $this->productRepository,
            $this->packageBindingRepo,
            $this->availabilityService,
            new ProductSaleTypeMatcher(),
            $this->sysConfigRepo,
        );
        // Si el vínculo gana, el matching automático nunca debe consultarse.
        $this->productRepository->expects($this->never())->method('findMatchingDestination');

        $selected = $this->resolver->select($account, $package);

        $this->assertSame('ref-bound', $selected->externalRef);
    }

    public function testFallsBackToAutomaticMatchingWhenBoundProductIsDisabled(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $bound = $this->createMock(CommunicationProduct::class);
        $bound->method('isEnabled')->willReturn(false);
        $binding = $this->createMock(CommunicationPackageProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);
        $auto = $this->createMock(CommunicationProduct::class);
        $auto->method('getExternalRef')->willReturn('ref-auto');

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->packageBindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->packageBindingRepo->method('findForPackageAndProvider')->willReturn($binding);
        $this->resolver = new ProviderDispatchResolver(
            $this->routingRepo,
            $this->productRepository,
            $this->packageBindingRepo,
            $this->availabilityService,
            new ProductSaleTypeMatcher(),
            $this->sysConfigRepo,
        );
        $this->productRepository->method('findMatchingDestination')->willReturn([$auto]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame('ref-auto', $selected->externalRef);
    }

    public function testFallsBackToAutomaticMatchingWhenBoundProductIsFromAnotherEnvironment(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $otherEnvironment = $this->createMock(Environment::class);
        $bound = $this->createMock(CommunicationProduct::class);
        $bound->method('isEnabled')->willReturn(true);
        $bound->method('getEnvironment')->willReturn($otherEnvironment);
        $binding = $this->createMock(CommunicationPackageProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);
        $auto = $this->createMock(CommunicationProduct::class);
        $auto->method('getExternalRef')->willReturn('ref-auto');

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->packageBindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->packageBindingRepo->method('findForPackageAndProvider')->willReturn($binding);
        $this->resolver = new ProviderDispatchResolver(
            $this->routingRepo,
            $this->productRepository,
            $this->packageBindingRepo,
            $this->availabilityService,
            new ProductSaleTypeMatcher(),
            $this->sysConfigRepo,
        );
        $this->productRepository->method('findMatchingDestination')->willReturn([$auto]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame('ref-auto', $selected->externalRef);
    }

    public function testSkipsAProviderThatIsNotAvailableAndTriesTheNextOne(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-dtone');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', priority: 0, id: 1), $this->routeScope('DTONE', priority: 10, id: 2)]);
        $this->availabilityService->method('canDispatchTo')
            ->willReturnCallback(fn ($provider) => $provider !== 'CSQ');
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
    }

    public function testSkipsAProviderWithNoProductCoveringTheTupleAndTriesTheNextOne(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-dtone');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', priority: 0, id: 1), $this->routeScope('DTONE', priority: 10, id: 2)]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')
            ->willReturnCallback(fn ($amount, $currency, $env, $provider) => $provider === 'DTONE' ? [$product] : []);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
    }

    public function testThrowsPackageNotDispatchableWhenNoProviderIsAvailable(): void
    {
        $account = $this->account(1);
        $package = $this->package();

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(false);

        try {
            $this->resolver->select($account, $package);
            $this->fail('Se esperaba MyCurrentException');
        } catch (MyCurrentException $e) {
            $this->assertSame('PACKAGE_NOT_DISPATCHABLE', $e->getCodeWork());
            $this->assertSame(409, $e->getCode());
        }
    }

    public function testThrowsPackageNotDispatchableWhenNoProductMatchesSaleType(): void
    {
        $account = $this->account(1);
        $package = $this->package();

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([]);

        $this->expectException(MyCurrentException::class);

        $this->resolver->select($account, $package);
    }

    public function testKillSwitchIgnoresRoutingTableAndTriesOnlyTheDefaultProvider(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-etecsa');

        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ProviderResolver::ROUTING_ENABLED_KEY ? '0' : null);
        $this->resolver = new ProviderDispatchResolver(
            $this->routingRepo,
            $this->productRepository,
            $this->packageBindingRepo,
            $this->availabilityService,
            new ProductSaleTypeMatcher(),
            $this->sysConfigRepo,
        );

        $this->routingRepo->expects($this->never())->method('findActiveRouteScopesForClient');
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        // ETECSA es el proveedor por defecto cuando sys_config no tiene
        // communications.provider.default seteado (mismo default que
        // ProviderResolver).
        $this->assertSame(CommunicationProviderEnum::ETECSA, $selected->provider);
    }

    public function testNoActiveRoutingRowsFallsBackToTheDefaultProvider(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-default');

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $selected->provider);
    }

    public function testPromotionalPackageNeverFallsBackToAutomaticMatchingWithoutABinding(): void
    {
        $account = $this->account(1);
        $package = $this->promotionalPackage();

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        // Sin vínculo explícito (setUp ya deja findForPackageAndProvider en null).
        $this->productRepository->expects($this->never())->method('findMatchingDestination');

        $this->expectException(MyCurrentException::class);

        $this->resolver->select($account, $package);
    }

    public function testPromotionalPackageSkipsAProviderWithoutABindingAndTriesTheNextOne(): void
    {
        $account = $this->account(1);
        $package = $this->promotionalPackage();
        $bound = $this->createMock(CommunicationProduct::class);
        $bound->method('getExternalRef')->willReturn('ref-dtone');
        $bound->method('isEnabled')->willReturn(true);
        $bound->method('getEnvironment')->willReturn(null);
        $binding = $this->createMock(CommunicationPackageProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', priority: 0, id: 1), $this->routeScope('DTONE', priority: 10, id: 2)]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->packageBindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->packageBindingRepo->method('findForPackageAndProvider')
            ->willReturnCallback(fn ($pkg, $provider) => $provider === 'DTONE' ? $binding : null);
        $this->resolver = new ProviderDispatchResolver(
            $this->routingRepo,
            $this->productRepository,
            $this->packageBindingRepo,
            $this->availabilityService,
            new ProductSaleTypeMatcher(),
            $this->sysConfigRepo,
        );
        $this->productRepository->expects($this->never())->method('findMatchingDestination');

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
        $this->assertSame('ref-dtone', $selected->externalRef);
    }

    public function testPromotionalPackageStillUsesAnExplicitBindingWhenPresent(): void
    {
        $account = $this->account(1);
        $package = $this->promotionalPackage();
        $bound = $this->createMock(CommunicationProduct::class);
        $bound->method('getExternalRef')->willReturn('ref-bound');
        $bound->method('isEnabled')->willReturn(true);
        $bound->method('getEnvironment')->willReturn(null);
        $binding = $this->createMock(CommunicationPackageProviderProduct::class);
        $binding->method('getProduct')->willReturn($bound);

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('ETECSA')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->packageBindingRepo = $this->createMock(CommunicationPackageProviderProductRepository::class);
        $this->packageBindingRepo->method('findForPackageAndProvider')->willReturn($binding);
        $this->resolver = new ProviderDispatchResolver(
            $this->routingRepo,
            $this->productRepository,
            $this->packageBindingRepo,
            $this->availabilityService,
            new ProductSaleTypeMatcher(),
            $this->sysConfigRepo,
        );

        $selected = $this->resolver->select($account, $package);

        $this->assertSame('ref-bound', $selected->externalRef);
    }

    // ---- Fase de categoría/scope (nuevo) ----

    public function testDiscardsARowScopedToAnotherEnvironment(): void
    {
        $account = $this->account(1, environmentId: 10);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-default-env');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', environmentId: 99)]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        // La única fila no aplica (otro entorno) → cae al proveedor por defecto.
        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $selected->provider);
    }

    public function testDiscardsARowScopedToAnotherSaleType(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-default');
        // ProductSaleTypeMatcher exige isMobileOrInternetService() para
        // considerar un producto elegible como 'recharge'.
        $product->method('isMobileOrInternetService')->willReturn(true);

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', saleType: 'sale')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package, 'recharge');

        $this->assertSame(CommunicationProviderEnum::ETECSA, $selected->provider);
    }

    public function testDiscardsARowScopedToAnotherServiceCategory(): void
    {
        $account = $this->account(1);
        $package = $this->packageWithService('Utilities', 'INTERNET');
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-default');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', serviceName: 'Mobile', subserviceName: 'AIRTIME')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $selected->provider);
    }

    public function testAServiceOnlyRowMatchesAnySubserviceOfThatCategory(): void
    {
        $account = $this->account(1);
        $package = $this->packageWithService('Mobile', 'DATA');
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-csq');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', serviceName: 'Mobile')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::CSQ, $selected->provider);
    }

    public function testTheMostSpecificApplicableRowWinsOverAWildcardRow(): void
    {
        $account = $this->account(1, environmentId: 10);
        $package = $this->packageWithService('Mobile', 'AIRTIME');
        $specificProduct = $this->createMock(CommunicationProduct::class);
        $specificProduct->method('getExternalRef')->willReturn('ref-csq');

        // Comodín total, creada primero (id menor) — si el orden fuera solo
        // por id/priority, ganaría esta.
        $wildcard = $this->routeScope('ETECSA', priority: 0, id: 1);
        // Específica a environment+service+subservice, creada después.
        $specific = $this->routeScope('CSQ', environmentId: 10, serviceName: 'Mobile', subserviceName: 'AIRTIME', priority: 100, id: 2);

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$wildcard, $specific]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')
            ->willReturnCallback(fn ($amount, $currency, $env, $provider) => $provider === 'CSQ' ? [$specificProduct] : []);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::CSQ, $selected->provider);
    }

    public function testTiesInSpecificityKeepPriorityThenIdOrder(): void
    {
        $account = $this->account(1);
        $package = $this->packageWithService('Mobile', 'AIRTIME');
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-dtone');

        // Misma especificidad (ambas comodín total) — debe respetarse el
        // orden priority ASC, id ASC que ya trae el repositorio.
        $first = $this->routeScope('CSQ', priority: 0, id: 1);
        $second = $this->routeScope('DTONE', priority: 10, id: 2);

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$first, $second]);
        $this->availabilityService->method('canDispatchTo')
            ->willReturnCallback(fn ($provider) => $provider !== 'CSQ');
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
    }

    // ---- Fallback provider (nuevo) ----

    public function testFallbackProviderIsTriedAfterItsOwnPrimaryProvider(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-fallback');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', fallbackProvider: 'DTONE')]);
        $this->availabilityService->method('canDispatchTo')
            ->willReturnCallback(fn ($provider) => $provider !== 'CSQ');
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
    }

    public function testFallbackProviderDuplicatedAsAnotherRowsPrimaryIsNotTriedTwice(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-dtone');

        // CSQ→DTONE como fallback, y DTONE también aparece como primario de
        // otra fila — DTONE debe intentarse una sola vez.
        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([
                $this->routeScope('CSQ', fallbackProvider: 'DTONE', priority: 0, id: 1),
                $this->routeScope('DTONE', priority: 10, id: 2),
            ]);
        $callCount = 0;
        $this->availabilityService->method('canDispatchTo')
            ->willReturnCallback(function ($provider) use (&$callCount) {
                $callCount++;

                return $provider !== 'CSQ';
            });
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->select($account, $package);

        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
        // CSQ, luego DTONE — nunca una tercera llamada repitiendo DTONE.
        $this->assertSame(2, $callCount);
    }

    public function testSelectExcludingSkipsAnAlreadyTriedProviderAndReturnsNullInsteadOfThrowing(): void
    {
        $account = $this->account(1);
        $package = $this->package();

        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([$this->routeScope('CSQ')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);

        $selected = $this->resolver->selectExcluding($account, $package, null, [CommunicationProviderEnum::CSQ]);

        $this->assertNull($selected);
    }

    public function testSelectExcludingFindsTheFallbackWhenThePrimaryIsExcluded(): void
    {
        $account = $this->account(1);
        $package = $this->package();
        $product = $this->createMock(CommunicationProduct::class);
        $product->method('getExternalRef')->willReturn('ref-fallback');

        $this->routingRepo->method('findActiveRouteScopesForClient')
            ->willReturn([$this->routeScope('CSQ', fallbackProvider: 'DTONE')]);
        $this->availabilityService->method('canDispatchTo')->willReturn(true);
        $this->productRepository->method('findMatchingDestination')->willReturn([$product]);

        $selected = $this->resolver->selectExcluding($account, $package, null, [CommunicationProviderEnum::CSQ]);

        $this->assertNotNull($selected);
        $this->assertSame(CommunicationProviderEnum::DTONE, $selected->provider);
    }
}
