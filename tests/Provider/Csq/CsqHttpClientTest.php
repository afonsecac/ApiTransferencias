<?php

namespace App\Tests\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\SysConfigRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @covers \App\Provider\Csq\CsqHttpClient
 */
class CsqHttpClientTest extends TestCase
{
    private function credentialsResolver(
        ?string $username = 'DEVELOPUS',
        ?string $password = 'p4ssw0rd',
        ?string $baseUrl = null,
        ?string $terminal = '173103',
        ?string $receiverEmail = 'ops@comremit.test',
        ?string $documentNumber = '5353615257',
    ): ProviderCredentialsResolver {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.csq.test.base_url', true, $baseUrl],
            ['provider.csq.test.username', true, $username],
            ['provider.csq.test.password', true, $password],
            ['provider.csq.test.terminal', true, $terminal],
            ['provider.csq.test.default_receiver_email', true, $receiverEmail],
            ['provider.csq.test.default_document_number', true, $documentNumber],
        ]);

        // El registro necesita el proveedor real para que el resolver sepa
        // qué claves buscar (getConfigSchema()) — el CsqHttpClient del
        // adaptador registrado no se usa (el resolver no lo invoca).
        $registry = new ProviderRegistry([
            new CsqCommunicationProvider(
                $this->createMock(CsqHttpClient::class),
                new \App\Provider\Csq\CsqStatusMapper(),
                new NullLogger(),
            ),
        ]);

        return new ProviderCredentialsResolver($sysConfigRepo, $registry);
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(provider: CommunicationProviderEnum::CSQ, environmentType: 'TEST');
    }

    // ---- computeSignature ----

    public function testComputeSignatureMatchesDocumentedDoubleSha256Formula(): void
    {
        $client = new CsqHttpClient(new MockHttpClient(), $this->credentialsResolver(), new NullLogger());

        $passwordHash = hash('sha256', 'p4ssw0rd');
        $saltHash = hash('sha256', '1700000000');
        $expected = hash('sha256', $passwordHash . $saltHash);

        $this->assertSame($expected, $client->computeSignature('p4ssw0rd', '1700000000'));
    }

    public function testComputeSignatureChangesWithTimestamp(): void
    {
        $client = new CsqHttpClient(new MockHttpClient(), $this->credentialsResolver(), new NullLogger());

        $this->assertNotSame(
            $client->computeSignature('p4ssw0rd', '1700000000'),
            $client->computeSignature('p4ssw0rd', '1700000001'),
        );
    }

    // ---- ping ----

    public function testPingSendsDocumentedAuthHeadersAndDefaultBaseUrl(): void
    {
        $requestHeaders = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestHeaders) {
            $requestHeaders = $options['normalized_headers'];

            $this->assertSame('GET', $method);
            $this->assertSame('https://evsb.csqworld.com/ping/pong', $url);

            return new MockResponse(json_encode(['rc' => 0, 'echo' => 'pong', 'ts' => 61460582]), ['http_code' => 200]);
        });

        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $result = $client->ping($this->context());

        $this->assertSame(['rc' => 0, 'echo' => 'pong', 'ts' => 61460582], $result);
        $this->assertSame(['U: DEVELOPUS'], $requestHeaders['u']);
        $this->assertArrayHasKey('st', $requestHeaders);
        $this->assertArrayHasKey('sh', $requestHeaders);
    }

    public function testPingUsesConfiguredBaseUrlWhenPresent(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('https://custom.csq.test/ping/hola', $url);

            return new MockResponse(json_encode(['rc' => 0, 'echo' => 'hola', 'ts' => 1]), ['http_code' => 200]);
        });

        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(baseUrl: 'https://custom.csq.test'), new NullLogger());

        $client->ping($this->context(), 'hola');
    }

    public function testPingThrowsWhenCredentialsMissing(): void
    {
        $httpClient = new MockHttpClient();
        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(username: null), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->ping($this->context());
    }

    public function testPingWrapsTransportErrors(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse('', ['http_code' => 500]);
        });
        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->ping($this->context());
    }

    public function testPingSendsAcceptApplicationJsonNotBareJson(): void
    {
        // Bug real (2026-08-03): 'Accept: json' (el valor "ejemplo" de la
        // doc) da 406 en los endpoints de negocio de CSQ — hay que mandar
        // el mimetype completo.
        $requestHeaders = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestHeaders) {
            $requestHeaders = $options['normalized_headers'];

            return new MockResponse(json_encode(['rc' => 0]), ['http_code' => 200]);
        });

        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());
        $client->ping($this->context());

        $this->assertSame(['Accept: application/json'], $requestHeaders['accept']);
    }

    public function testDoesNotSetAcceptEncodingSoTheTransportCanAutoDecompress(): void
    {
        // Bug real (2026-08-09): fijar 'Accept-Encoding: gzip' a mano
        // desactivaba la descompresión automática de Symfony HttpClient —
        // /product/portfolio (que sí viene comprimido, a diferencia de
        // /ping) llegaba como binario gzip crudo a json_decode y reventaba
        // con "Control character error". No fijar el header deja que el
        // transporte negocie y descomprima solo.
        $requestHeaders = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestHeaders) {
            $requestHeaders = $options['normalized_headers'];

            return new MockResponse(json_encode(['rc' => 0]), ['http_code' => 200]);
        });

        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());
        $client->ping($this->context());

        $this->assertArrayNotHasKey('accept-encoding', $requestHeaders);
    }

    public function testPingWrapsDecodingErrorsOnMalformedResponseBody(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse('esto no es json', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
        });
        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->ping($this->context());
    }

    // ---- getPortfolio ----

    public function testGetPortfolioSendsDocumentedRequestAndDecodesBody(): void
    {
        $requestedUrl = null;
        $requestHeaders = null;
        $payload = [[
            'terminalId' => 173103,
            'products' => [
                ['articleId' => 7951, 'name' => 'Cubacel  Pack Combos', 'countryId' => 192],
            ],
        ]];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestedUrl, &$requestHeaders, $payload) {
            $requestedUrl = $url;
            $requestHeaders = $options['normalized_headers'];

            $this->assertSame('GET', $method);

            return new MockResponse(json_encode($payload), ['http_code' => 200]);
        });

        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());
        $result = $client->getPortfolio($this->context());

        $this->assertSame('https://evsb.csqworld.com/product/portfolio', $requestedUrl);
        $this->assertSame(['U: DEVELOPUS'], $requestHeaders['u']);
        $this->assertSame($payload, $result);
    }

    public function testGetPortfolioWrapsTransportErrors(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse('', ['http_code' => 503]);
        });
        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->getPortfolio($this->context());
    }

    public function testGetPortfolioThrowsWhenCredentialsMissing(): void
    {
        $httpClient = new MockHttpClient();
        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(password: null), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->getPortfolio($this->context());
    }

    // ---- purchase ----

    public function testPurchaseSendsDestinationAmountAccountAndLocalDateTime(): void
    {
        // Confirmado en vivo (2026-08-10, compra real exitosa contra
        // Cubacel/7854, supplierreference "1786346034143"; reconfirmado
        // 2026-08-11): dynamicProductId/receiverEmail/documentNumber son
        // obligatorios en TODA compra CSQ, incluso para un artículo que
        // Get Parameters marca dynamic:false — ese endpoint no describe
        // fielmente lo que Purchase exige.
        $requestedUrl = null;
        $requestBody = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestedUrl, &$requestBody) {
            $requestedUrl = $url;
            $requestBody = json_decode($options['body'], true);

            $this->assertSame('POST', $method);

            return new MockResponse(json_encode(['rc' => 0, 'items' => [['resultcode' => '10']]]), ['http_code' => 200]);
        });

        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());
        $client->purchase($this->context(), 7951, 100042, '53500000', 220000);

        $this->assertSame('https://evsb.csqworld.com/pre-paid/recharge/purchase/173103/7951/100042', $requestedUrl);
        $this->assertSame(220000, $requestBody['destinationAmountX100']);
        $this->assertSame('53500000', $requestBody['account']);
        $this->assertArrayHasKey('localDateTime', $requestBody);
        $this->assertSame('7951', $requestBody['dynamicProductId']);
        $this->assertSame('ops@comremit.test', $requestBody['receiverEmail']);
        $this->assertSame('5353615257', $requestBody['documentNumber']);
    }

    public function testPurchaseWrapsTransportErrors(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse('', ['http_code' => 503]);
        });
        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->purchase($this->context(), 7951, 100042, '53500000', 220000);
    }

    // ---- getParameters ----

    /**
     * GET /pre-paid/recharge/parameters/{terminal}/{articleId} — confirmado
     * en vivo el 2026-08-31: la respuesta trae `parameters[].labels` con la
     * semántica real del campo "account" (siempre el mismo nombre de
     * campo, ver purchase()) — p.ej. "Nauta email" para Nauta CUP (7855) vs
     * "Phone Number" para Cubacel (7854). Es la única señal que CSQ da para
     * distinguirlos, ver CsqCommunicationProvider::resolveRequiredIdentifierFields().
     */
    public function testGetParametersSendsDocumentedRequestAndDecodesBody(): void
    {
        $requestedUrl = null;
        $payload = ['rc' => 0, 'message' => 'OK', 'dynamic' => false, 'dynamicField' => 'account', 'parameters' => [
            ['field' => 'account', 'labels' => ['en' => 'Nauta email', 'es' => 'Correo electronico de Nauta']],
        ]];

        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl, $payload) {
            $requestedUrl = $url;
            $this->assertSame('GET', $method);

            return new MockResponse(json_encode($payload), ['http_code' => 200]);
        });

        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());
        $result = $client->getParameters($this->context(), 7855);

        $this->assertSame('https://evsb.csqworld.com/pre-paid/recharge/parameters/173103/7855', $requestedUrl);
        $this->assertSame($payload, $result);
    }

    public function testGetParametersWrapsTransportErrors(): void
    {
        $httpClient = new MockHttpClient(function () {
            return new MockResponse('', ['http_code' => 503]);
        });
        $client = new CsqHttpClient($httpClient, $this->credentialsResolver(), new NullLogger());

        $this->expectException(MyCurrentException::class);

        $client->getParameters($this->context(), 7855);
    }
}
