<?php

namespace App\Tests\Provider\DTOne;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderStatusQuery;
use App\Provider\Contract\PromotionCatalogQuery;
use App\Provider\Contract\RechargeRequest;
use App\Provider\DTOne\DTOneCommunicationProvider;
use App\Provider\DTOne\DTOneHttpClient;
use App\Provider\DTOne\DTOneStatusMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Provider\DTOne\DTOneCommunicationProvider
 */
class DTOneCommunicationProviderTest extends TestCase
{
    private DTOneHttpClient&MockObject $client;
    private DTOneCommunicationProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(DTOneHttpClient::class);
        // DTOneStatusMapper no tiene dependencias: se usa real, no mock.
        $this->provider = new DTOneCommunicationProvider($this->client, new DTOneStatusMapper(), new NullLogger());
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(provider: CommunicationProviderEnum::DTONE, environmentType: 'TEST');
    }

    public function testGetCodeAndCapabilities(): void
    {
        $this->assertSame(CommunicationProviderEnum::DTONE, $this->provider->getCode());
        $this->assertSame([
            ProviderCapabilityEnum::RECHARGE,
            ProviderCapabilityEnum::PACKAGE_SALE,
            ProviderCapabilityEnum::BALANCE,
            ProviderCapabilityEnum::CATALOG,
        ], $this->provider->getCapabilities());
    }

    public function testRechargeBuildsTransactionBodyAndMapsDispatch(): void
    {
        // Número local de 8 dígitos (convenio de ETECSA, sin código de
        // país) — DTOne exige E.164 completo, ver toE164Cuba().
        $this->client->expects($this->once())
            ->method('createTransaction')
            ->with(
                $this->anything(),
                'TX-1',
                ['external_id' => 'TX-1', 'product_id' => 555, 'auto_confirm' => true, 'credit_party_identifier' => ['mobile_number' => '+5355501234']],
            )
            ->willReturn(['id' => 'dtone-ref', 'status' => ['id' => 20000, 'message' => 'submitted', 'class' => ['id' => 5, 'message' => 'SUBMITTED']]]);

        $request = new RechargeRequest(
            transactionId: 'TX-1',
            phoneNumber: '55501234',
            productExternalId: '555',
            destinationAmount: 5.0,
            destinationUnit: 'USD',
        );

        $result = $this->provider->recharge($this->context(), $request);

        // ACCEPTED, no PENDING: es el resultado del despacho inicial (ver
        // DTOneStatusMapper::mapDispatch()) — dispara el re-chequeo
        // diferido en CommunicationSaleService.
        $this->assertSame(ProviderOutcomeEnum::ACCEPTED, $result->outcome);
        $this->assertSame('dtone-ref', $result->providerReference);
    }

    /**
     * @dataProvider phoneNumberFormatProvider
     */
    public function testRechargeConvertsPhoneNumberToE164Cuba(string $inputPhoneNumber, string $expectedMobileNumber): void
    {
        $this->client->expects($this->once())
            ->method('createTransaction')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body) => $body['credit_party_identifier']['mobile_number'] === $expectedMobileNumber),
            )
            ->willReturn(['id' => 'dtone-ref', 'status' => ['id' => 20000, 'message' => 'submitted', 'class' => ['id' => 5, 'message' => 'SUBMITTED']]]);

        $request = new RechargeRequest(
            transactionId: 'TX-phone',
            phoneNumber: $inputPhoneNumber,
            productExternalId: '555',
            destinationAmount: 5.0,
            destinationUnit: 'USD',
        );

        $this->provider->recharge($this->context(), $request);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function phoneNumberFormatProvider(): iterable
    {
        yield 'local de 8 dígitos, sin código de país' => ['55501234', '+5355501234'];
        yield '10 dígitos, ya incluye el código de país' => ['5355501234', '+5355501234'];
        yield 'ya viene en E.164, se respeta tal cual' => ['+5355501234', '+5355501234'];
    }

    /**
     * DTONE_CLIENT_ERROR es una respuesta 4xx definitiva de DTOne (p.ej. el
     * 404 "Product is not available in your account" real que confirmamos
     * el 2026-08-03) — la transacción nunca se creó, así que se rechaza de
     * inmediato en vez de quedar en UNKNOWN esperando un estado que nunca
     * va a llegar (ver mapDispatchException()).
     */
    public function testRechargeMapsDefiniteClientErrorToRejected(): void
    {
        $this->client->method('createTransaction')
            ->willThrowException(new MyCurrentException('DTONE_CLIENT_ERROR', 'Product is not available in your account', 404));

        $request = new RechargeRequest(
            transactionId: 'TX-2',
            phoneNumber: '5550001234',
            productExternalId: '555',
            destinationAmount: 5.0,
            destinationUnit: 'USD',
        );

        $result = $this->provider->recharge($this->context(), $request);

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
        $this->assertSame('Product is not available in your account', $result->message);
    }

    /**
     * @dataProvider ambiguousDtoneErrorProvider
     */
    public function testRechargeMapsAmbiguousErrorsToUnknown(string $codeWork): void
    {
        $this->client->method('createTransaction')
            ->willThrowException(new MyCurrentException($codeWork, 'boom', 503));

        $request = new RechargeRequest(
            transactionId: 'TX-2b',
            phoneNumber: '5550001234',
            productExternalId: '555',
            destinationAmount: 5.0,
            destinationUnit: 'USD',
        );

        $result = $this->provider->recharge($this->context(), $request);

        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
        $this->assertSame('boom', $result->message);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ambiguousDtoneErrorProvider(): iterable
    {
        // Ninguno de estos nos dice con certeza si DTOne procesó la
        // petición — nunca deben rechazarse de inmediato.
        yield 'timeout de transporte' => ['DTONE_GATEWAY_TIMEOUT'];
        yield 'error 5xx de DTOne' => ['DTONE_SERVER_ERROR'];
        yield 'credenciales faltantes' => ['PROVIDER_CREDENTIALS_MISSING'];
    }

    public function testFetchRechargeStatusMapsFoundTransaction(): void
    {
        $this->client->method('findTransactionByExternalId')
            ->with($this->anything(), 'TX-3')
            ->willReturn(['id' => 'dtone-ref', 'status' => ['id' => 20000, 'class' => ['id' => 7]]]);

        $result = $this->provider->fetchRechargeStatus($this->context(), new ProviderStatusQuery(transactionId: 'TX-3'));

        $this->assertSame(ProviderOutcomeEnum::COMPLETED, $result->outcome);
    }

    /**
     * DTOne usa auto_confirm=true: una transacción real queda indexada de
     * inmediato, así que "no encontrada" aquí es una señal confiable de que
     * nunca se creó — se puede rechazar en vez de dejarla reintentando para
     * siempre (ver fetchStatus()).
     */
    public function testFetchRechargeStatusRejectsWhenTransactionNeverFound(): void
    {
        $this->client->method('findTransactionByExternalId')->willReturn(null);

        $result = $this->provider->fetchRechargeStatus($this->context(), new ProviderStatusQuery(transactionId: 'TX-4'));

        $this->assertSame(ProviderOutcomeEnum::REJECTED, $result->outcome);
    }

    public function testFetchRechargeStatusMapsTransportErrorToUnknown(): void
    {
        $this->client->method('findTransactionByExternalId')
            ->willThrowException(new MyCurrentException('DTONE_GATEWAY_TIMEOUT', 'timeout', 503));

        $result = $this->provider->fetchRechargeStatus($this->context(), new ProviderStatusQuery(transactionId: 'TX-5'));

        $this->assertSame(ProviderOutcomeEnum::UNKNOWN, $result->outcome);
    }

    /**
     * Forma real confirmada en vivo el 2026-08-20 contra el sandbox TEST:
     * un array plano de cuentas, `unit`/`available` (no `data`/`currency`/
     * `amount`, que es lo que el código asumía antes de este fix — por
     * eso "probar conexión" siempre mostraba el saldo vacío para DTOne).
     */
    public function testGetPlatformBalanceParsesFlatAccountsArray(): void
    {
        $this->client->method('getBalances')->willReturn([
            ['available' => 123.45, 'credit_limit' => 0, 'holding' => 0, 'id' => 1, 'unit' => 'USD', 'unit_type' => 'CURRENCY'],
            ['available' => 10.0, 'credit_limit' => 0, 'holding' => 0, 'id' => 2, 'unit' => 'EUR', 'unit_type' => 'CURRENCY'],
        ]);

        $result = $this->provider->getPlatformBalance($this->context());

        $this->assertSame(['USD' => 123.45, 'EUR' => 10.0], $result->amounts);
    }

    public function testGetPlatformBalanceIgnoresHoldingAndCreditLimit(): void
    {
        $this->client->method('getBalances')->willReturn([
            ['available' => 99907.81, 'credit_limit' => 500.0, 'holding' => 250.0, 'id' => 1, 'unit' => 'EUR', 'unit_type' => 'CURRENCY'],
        ]);

        $result = $this->provider->getPlatformBalance($this->context());

        $this->assertSame(['EUR' => 99907.81], $result->amounts);
    }

    public function testGetPlatformBalanceSkipsNonCurrencyEntries(): void
    {
        $this->client->method('getBalances')->willReturn([
            ['available' => 5000.0, 'id' => 1, 'unit' => 'CREDIT', 'unit_type' => 'CREDIT_LINE'],
            ['available' => 123.45, 'id' => 2, 'unit' => 'USD', 'unit_type' => 'CURRENCY'],
        ]);

        $result = $this->provider->getPlatformBalance($this->context());

        $this->assertSame(['USD' => 123.45], $result->amounts);
    }

    public function testFetchProductsFiltersByCubaCountryIsoCode(): void
    {
        // Sin este filtro en origen, DTOne devuelve su catálogo mundial
        // completo (decenas de miles de productos) y el proceso agota la
        // memoria antes de terminar de paginar — confirmado el 2026-08-02.
        $this->client->expects($this->once())
            ->method('iterateProducts')
            ->with($this->anything(), ['country_iso_code' => 'CUB'])
            ->willReturn((function () {
                yield from [];
            })());

        iterator_to_array($this->provider->fetchProducts($this->context()));
    }

    /**
     * @dataProvider mobileOrInternetServiceProvider
     */
    public function testFetchProductsComputesIsMobileOrInternetService(?string $service, ?string $subservice, bool $expected): void
    {
        $item = [
            'id' => 1,
            'name' => 'Producto de prueba',
            'type' => 'FIXED_VALUE_RECHARGE',
            'destination' => ['amount' => 5, 'unit' => 'USD'],
            'prices' => ['wholesale' => ['amount' => 4.5, 'unit' => 'USD']],
        ];
        if ($service !== null) {
            $item['service']['name'] = $service;
        }
        if ($subservice !== null) {
            $item['service']['subservice']['name'] = $subservice;
        }

        $this->client->method('iterateProducts')->willReturn((function () use ($item) {
            yield $item;
        })());

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertSame($expected, $products[0]->isMobileOrInternetService);
    }

    public function testFetchProductsPreservesServiceShapeForClientPackageCreation(): void
    {
        // ClientCatalogImportService::createClientPackageIfMissing() usa
        // este campo tal cual (mismo shape que CommunicationClientPackage::$service)
        // para crear la asignación automáticamente — no debe perderse nada.
        $this->client->method('iterateProducts')->willReturn((function () {
            yield [
                'id' => 1,
                'name' => 'Producto de prueba',
                'type' => 'FIXED_VALUE_RECHARGE',
                'destination' => ['amount' => 5, 'unit' => 'USD'],
                'prices' => ['wholesale' => ['amount' => 4.5, 'unit' => 'USD']],
                'service' => ['name' => 'Mobile', 'subservice' => ['name' => 'Airtime']],
            ];
        })());

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertSame(
            ['name' => 'Mobile', 'subservice' => ['name' => 'Airtime']],
            $products[0]->service,
        );
    }

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function mobileOrInternetServiceProvider(): iterable
    {
        // Verificado contra el catálogo real de Cuba el 2026-08-02.
        yield 'Mobile/Airtime (CubaCel)' => ['Mobile', 'Airtime', true];
        yield 'Mobile/Data (CubaCel)' => ['Mobile', 'Data', true];
        yield 'Mobile/Bundle (CubaCel, incluye bundles con equipo)' => ['Mobile', 'Bundle', true];
        yield 'Utilities/Internet (Nauta)' => ['Utilities', 'Internet', true];
        yield 'Utilities/Landline (Nauta Hogar)' => ['Utilities', 'Landline', true];
        yield 'Utilities sin subservice (SIM card, equipo físico)' => ['Utilities', null, false];
        yield 'Gift Cards/Food (Jabalina, Mandao, Ko Mercado)' => ['Gift Cards', 'Food', false];
        yield 'sin service en absoluto' => [null, null, false];
    }

    public function testFetchProductsMapsFixedValueRecharge(): void
    {
        $this->client->method('iterateProducts')->willReturn((function () {
            yield [
                'id' => 42,
                'name' => 'Recarga 5 USD',
                'type' => 'FIXED_VALUE_RECHARGE',
                'is_active' => true,
                'destination' => ['amount' => 5, 'unit' => 'USD'],
                'prices' => ['wholesale' => ['amount' => 4.5, 'unit' => 'USD']],
                'benefits' => [['type' => 'CREDITS']],
            ];
            yield ['no_id_field' => true];
        })());

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(1, $products);
        $this->assertSame('42', $products[0]->externalId);
        $this->assertSame('Recarga 5 USD', $products[0]->name);
        $this->assertSame('FIXED_VALUE_RECHARGE', $products[0]->productTypeRaw);
        $this->assertSame(5.0, $products[0]->destinationAmount);
        $this->assertSame('USD', $products[0]->destinationUnit);
        $this->assertSame(4.5, $products[0]->wholesalePrice);
        $this->assertTrue($products[0]->enabled);
    }

    public function testFetchProductsMapsFixedValuePinPurchase(): void
    {
        $this->client->method('iterateProducts')->willReturn((function () {
            yield [
                'id' => 99,
                'name' => 'Gift card 10 USD',
                'type' => 'FIXED_VALUE_PIN_PURCHASE',
                'is_active' => true,
                'destination' => ['amount' => 10, 'unit' => 'USD'],
                'prices' => ['wholesale' => ['amount' => 9.0, 'unit' => 'USD']],
            ];
        })());

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(1, $products);
        $this->assertSame('FIXED_VALUE_PIN_PURCHASE', $products[0]->productTypeRaw);
    }

    /**
     * @dataProvider unsupportedProductTypeProvider
     */
    public function testFetchProductsSkipsUnsupportedTypes(?string $productType): void
    {
        $this->client->method('iterateProducts')->willReturn((function () use ($productType) {
            yield [
                'id' => 1,
                'name' => 'No soportado',
                'type' => $productType,
                'destination' => ['min_amount' => 25, 'max_amount' => 50, 'unit' => 'USD'],
            ];
        })());

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertCount(0, $products);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unsupportedProductTypeProvider(): iterable
    {
        yield 'RANGED_VALUE_RECHARGE' => ['RANGED_VALUE_RECHARGE'];
        yield 'RANGED_VALUE_PIN_PURCHASE' => ['RANGED_VALUE_PIN_PURCHASE'];
        yield 'tipo desconocido' => ['ALGO_NUEVO_NO_DOCUMENTADO'];
        yield 'sin type' => [null];
    }

    public function testFetchProductsReadsMinMaxAmountFieldNamesFromDtone(): void
    {
        // Aunque hoy se filtran, si algún día se admiten hay que leer los
        // nombres de campo REALES de DTOne (min_amount/max_amount), no
        // min/max — confirmado contra developers.dtone.com. Se verifica
        // aquí con un tipo soportado para no depender del filtro.
        $this->client->method('iterateProducts')->willReturn((function () {
            yield [
                'id' => 7,
                'name' => 'Rango de prueba',
                'type' => 'FIXED_VALUE_RECHARGE',
                'destination' => ['amount' => 5, 'min_amount' => 25, 'max_amount' => 50, 'unit' => 'USD'],
                'prices' => ['wholesale' => ['amount' => 4.5, 'unit' => 'USD']],
            ];
        })());

        $products = iterator_to_array($this->provider->fetchProducts($this->context()));

        $this->assertSame(25.0, $products[0]->destinationMinAmount);
        $this->assertSame(50.0, $products[0]->destinationMaxAmount);
    }

    private function query(array $amounts = [500.0, 625.0]): PromotionCatalogQuery
    {
        return new PromotionCatalogQuery(
            destinationCurrency: 'CUP',
            destinationAmounts: $amounts,
            activeFrom: new \DateTimeImmutable('2026-08-18T00:00:00+00:00'),
            activeTo: new \DateTimeImmutable('2026-08-25T23:59:00+00:00'),
        );
    }

    private function catalogProduct(int $id, float $amount, string $name = 'Producto'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => 'FIXED_VALUE_RECHARGE',
            'is_active' => true,
            'destination' => ['amount' => $amount, 'unit' => 'CUP'],
            'prices' => ['wholesale' => ['amount' => $amount / 25, 'unit' => 'USD']],
        ];
    }

    public function testFetchPromotionProductsCrossReferencesLivePromotionsAgainstFullCatalog(): void
    {
        // La promoción vigente (35719) referencia productos por id/name/type
        // únicamente — sin destinationAmount, así que hay que cruzar contra
        // fetchProducts() (iterateProducts) para obtener la tupla completa.
        $this->client->method('iteratePromotions')->willReturn((function () {
            yield [
                'id' => 6999,
                'start_date' => '2026-08-15T00:00:00.000Z',
                'end_date' => '2026-08-31T00:00:00.000Z',
                'products' => [['id' => 35719, 'name' => '500 CUP', 'type' => 'FIXED_VALUE_RECHARGE']],
            ];
        })());
        $this->client->method('iterateProducts')->willReturn((function () {
            yield $this->catalogProduct(35719, 500.0);
            // Mismo monto (625), pero NO referenciado por ninguna promoción vigente.
            yield $this->catalogProduct(35733, 625.0);
        })());

        $products = iterator_to_array($this->provider->fetchPromotionProducts($this->context(), $this->query()));

        $this->assertCount(1, $products);
        $this->assertSame('35719', $products[0]->externalId);
    }

    public function testFetchPromotionProductsExcludesPromotionsOutsideTheRequestedWindow(): void
    {
        $this->client->method('iteratePromotions')->willReturn((function () {
            yield [
                'id' => 5000,
                'start_date' => '2026-01-01T00:00:00.000Z',
                'end_date' => '2026-01-31T00:00:00.000Z',
                'products' => [['id' => 35719, 'name' => '500 CUP', 'type' => 'FIXED_VALUE_RECHARGE']],
            ];
        })());
        $this->client->expects($this->never())->method('iterateProducts');

        $products = iterator_to_array($this->provider->fetchPromotionProducts($this->context(), $this->query()));

        $this->assertCount(0, $products);
    }

    public function testFetchPromotionProductsExcludesProductsWhoseAmountDoesNotMatchAnyTramo(): void
    {
        $this->client->method('iteratePromotions')->willReturn((function () {
            yield [
                'id' => 6999,
                'start_date' => '2026-08-15T00:00:00.000Z',
                'end_date' => '2026-08-31T00:00:00.000Z',
                'products' => [['id' => 99999, 'name' => '900 CUP', 'type' => 'FIXED_VALUE_RECHARGE']],
            ];
        })());
        $this->client->method('iterateProducts')->willReturn((function () {
            // 900 CUP no está entre los tramos pedidos (500, 625).
            yield $this->catalogProduct(99999, 900.0);
        })());

        $products = iterator_to_array($this->provider->fetchPromotionProducts($this->context(), $this->query()));

        $this->assertCount(0, $products);
    }
}
