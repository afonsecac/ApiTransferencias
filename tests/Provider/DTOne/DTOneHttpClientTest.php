<?php

namespace App\Tests\Provider\DTOne;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\DTOne\DTOneCommunicationProvider;
use App\Provider\DTOne\DTOneHttpClient;
use App\Provider\DTOne\DTOneStatusMapper;
use App\Provider\ProviderRegistry;
use App\Repository\SysConfigRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @covers \App\Provider\DTOne\DTOneHttpClient
 */
class DTOneHttpClientTest extends TestCase
{
    private function credentialsResolver(?string $apiKey = 'key', ?string $apiSecret = 'secret', ?string $baseUrl = null): ProviderCredentialsResolver
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.test.base_url', true, $baseUrl],
            ['provider.dtone.test.api_key', true, $apiKey],
            ['provider.dtone.test.api_secret', true, $apiSecret],
        ]);

        // El registro necesita el proveedor real para que el resolver sepa
        // qué claves buscar (getConfigSchema()) — el cliente HTTP interno del
        // adaptador registrado no se usa (el resolver no lo invoca).
        // DTOneStatusMapper es `final`: se instancia real, no se dobla.
        $registry = new ProviderRegistry([
            new DTOneCommunicationProvider(
                $this->createMock(DTOneHttpClient::class),
                new DTOneStatusMapper(),
                $this->createMock(\Psr\Log\LoggerInterface::class),
            ),
        ]);

        return new ProviderCredentialsResolver($sysConfigRepo, $registry);
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(provider: CommunicationProviderEnum::DTONE, environmentType: 'TEST', correlationId: 'ext-1');
    }

    public function testCreateTransactionSendsBasicAuthAndJsonBody(): void
    {
        $requestBody = null;
        $requestOptions = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestBody, &$requestOptions) {
            $requestBody = $options['body'];
            $requestOptions = $options;

            $this->assertSame('POST', $method);
            $this->assertSame('https://preprod-dvs-api.dtone.com/v1/async/transactions', $url);

            return new MockResponse(json_encode(['id' => 'txn-1', 'status' => ['id' => 5]]), ['http_code' => 200]);
        });

        $client = new DTOneHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $result = $client->createTransaction($this->context(), 'ext-1', ['external_id' => 'ext-1', 'product_id' => 42]);

        $this->assertSame(['id' => 'txn-1', 'status' => ['id' => 5]], $result);
        $this->assertStringContainsString('"external_id":"ext-1"', $requestBody);
        $this->assertSame(
            ['Authorization: Basic ' . base64_encode('key:secret')],
            $requestOptions['normalized_headers']['authorization'],
        );
    }

    public function testUsesConfiguredBaseUrlWhenPresent(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('https://custom.dtone.test/v1/balances', $url);

            return new MockResponse(json_encode(['data' => []]), ['http_code' => 200]);
        });

        $client = new DTOneHttpClient(
            $httpClient,
            $this->credentialsResolver(baseUrl: 'https://custom.dtone.test'),
            new NullLogger(),
        );

        $client->getBalances($this->context());
    }

    public function testThrowsCredentialsMissingWhenApiKeyAbsent(): void
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('No debería llamarse a la API sin credenciales.');
        });

        $client = new DTOneHttpClient($httpClient, $this->credentialsResolver(apiKey: null), new NullLogger());

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Faltan credenciales de DTOne');

        $client->getBalances($this->context());
    }

    public function testDuplicateExternalIdIsResolvedTransparentlyViaLookup(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$calls) {
            $calls++;

            if ($calls === 1) {
                $errorBody = json_encode(['errors' => [['code' => '1007001', 'message' => 'external_id already used']]]);

                return new MockResponse($errorBody, ['http_code' => 422]);
            }

            $this->assertSame('GET', $method);
            $this->assertStringContainsString('/v1/transactions', $url);

            return new MockResponse(json_encode(['data' => [['id' => 'txn-existing', 'status' => ['id' => 7]]]]), ['http_code' => 200]);
        });

        $client = new DTOneHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $result = $client->createTransaction($this->context(), 'ext-1', ['external_id' => 'ext-1', 'product_id' => 42]);

        $this->assertSame(['id' => 'txn-existing', 'status' => ['id' => 7]], $result);
        $this->assertSame(2, $calls);
    }

    public function testDuplicateExternalIdUnresolvedThrowsWhenLookupReturnsNothing(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                $errorBody = json_encode(['errors' => [['code' => '1007001']]]);

                return new MockResponse($errorBody, ['http_code' => 422]);
            }

            return new MockResponse(json_encode(['data' => []]), ['http_code' => 200]);
        });

        $client = new DTOneHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('DTOne reportó external_id duplicado');

        $client->createTransaction($this->context(), 'ext-1', ['external_id' => 'ext-1', 'product_id' => 42]);
    }

    public function testOtherClientErrorsAreTranslatedToMyCurrentException(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse(json_encode(['errors' => [['code' => '1006001', 'message' => 'Insufficient balance']]]), ['http_code' => 400]);
        });

        $client = new DTOneHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->createTransaction($this->context(), 'ext-1', ['external_id' => 'ext-1', 'product_id' => 42]);
    }

    public function testIterateProductsFollowsPagination(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$calls) {
            $calls++;
            $page = $calls === 1 ? [['id' => 1], ['id' => 2]] : [['id' => 3]];
            $totalPages = $calls === 1 ? 2 : 2;

            return new MockResponse(json_encode(['data' => $page]), [
                'http_code' => 200,
                'response_headers' => ["x-total-pages: {$totalPages}"],
            ]);
        });

        $client = new DTOneHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $items = iterator_to_array($client->iterateProducts($this->context()));

        $this->assertCount(3, $items);
        $this->assertSame(2, $calls);
    }
}
