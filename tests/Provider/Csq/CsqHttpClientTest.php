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
    private function credentialsResolver(?string $username = 'DEVELOPUS', ?string $password = 'p4ssw0rd', ?string $baseUrl = null): ProviderCredentialsResolver
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.csq.test.base_url', true, $baseUrl],
            ['provider.csq.test.username', true, $username],
            ['provider.csq.test.password', true, $password],
        ]);

        // El registro necesita el proveedor real para que el resolver sepa
        // qué claves buscar (getConfigSchema()) — el CsqHttpClient del
        // adaptador registrado no se usa (el resolver no lo invoca).
        $registry = new ProviderRegistry([
            new CsqCommunicationProvider($this->createMock(CsqHttpClient::class)),
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
}
