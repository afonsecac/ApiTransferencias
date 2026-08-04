<?php

namespace App\Tests\Service\Etecsa;

use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\Etecsa\EtecsaGatewayClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\Etecsa\EtecsaGatewayClient::ping
 */
class EtecsaGatewayClientPingTest extends TestCase
{
    private function credentialsResolver(?string $apiKey = 'k3y', ?string $baseUrl = 'https://etecsa.example'): ProviderCredentialsResolver
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.etecsa.test.base_url', true, $baseUrl],
            ['provider.etecsa.test.api_key', true, $apiKey],
        ]);

        $adapter = new class implements CommunicationProviderInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::ETECSA;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [
                    new ProviderConfigField('base_url', 'URL base', required: true, secret: false),
                    new ProviderConfigField('api_key', 'API key', required: true, secret: true),
                ];
            }
        };

        return new ProviderCredentialsResolver($sysConfigRepo, new ProviderRegistry([$adapter]));
    }

    private function client(\Closure $responseFactory, ?string $apiKey = 'k3y'): EtecsaGatewayClient
    {
        return new EtecsaGatewayClient(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(Security::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(MailerInterface::class),
            new NullLogger(),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(EnvironmentRepository::class),
            $this->createMock(SysConfigRepository::class),
            $this->createMock(SerializerInterface::class),
            new MockHttpClient($responseFactory),
            $this->credentialsResolver(apiKey: $apiKey),
            new NullLogger(),
            'TEST_PHONE',
        );
    }

    private function environment(): Environment
    {
        return (new Environment())->setType('TEST')->setBasePath('https://legacy.example');
    }

    public function testPingSendsApiKeyHeaderAndReturns200Body(): void
    {
        $requestHeaders = null;
        $client = $this->client(function (string $method, string $url, array $options) use (&$requestHeaders) {
            $requestHeaders = $options['normalized_headers'];
            $this->assertSame('GET', $method);
            $this->assertSame('https://etecsa.example/ping', $url);

            return new MockResponse(json_encode(['environment' => 'TEST', 'available' => true, 'latencyMs' => 42]), ['http_code' => 200]);
        });

        $result = $client->ping($this->environment());

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['available']);
        $this->assertSame(['X-Api-Key: k3y'], $requestHeaders['x-api-key']);
    }

    public function testPingReadsBodyOn503WithoutThrowing(): void
    {
        $client = $this->client(function () {
            return new MockResponse(
                json_encode(['environment' => 'TEST', 'available' => false, 'error' => 'breaker open']),
                ['http_code' => 503],
            );
        });

        $result = $client->ping($this->environment());

        $this->assertSame(503, $result['status']);
        $this->assertFalse($result['body']['available']);
        $this->assertSame('breaker open', $result['body']['error']);
    }

    public function testPingReadsBodyOn403WithoutThrowing(): void
    {
        $client = $this->client(function () {
            return new MockResponse(json_encode(['error' => 'missing scope ping:read']), ['http_code' => 403]);
        });

        $result = $client->ping($this->environment());

        $this->assertSame(403, $result['status']);
    }

    public function testPingWrapsTransportFailureAsGatewayTimeout(): void
    {
        $client = $this->client(function () {
            return new MockResponse('', ['error' => 'Connection refused']);
        });

        $this->expectException(MyCurrentException::class);

        $client->ping($this->environment());
    }
}
