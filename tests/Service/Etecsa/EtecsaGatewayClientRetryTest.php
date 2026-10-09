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
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @covers \App\Service\Etecsa\EtecsaGatewayClient::retrySale
 */
class EtecsaGatewayClientRetryTest extends TestCase
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
            new Serializer([new ObjectNormalizer()], [new JsonEncoder()]),
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

    public function testRetrySaleSendsExpectedBodyInCheckMode(): void
    {
        $sentBody = null;
        $client = $this->client(function (string $method, string $url, array $options) use (&$sentBody) {
            $sentBody = json_decode($options['body'], true);
            $this->assertSame('POST', $method);
            $this->assertSame('https://etecsa.example/sale/retry', $url);

            return new MockResponse(json_encode([
                'transactionId' => '2610080101856',
                'environment' => 'TEST',
                'mode' => 'check',
                'previousStatus' => 'Failed',
                'status' => 'Completed',
                'action' => 'synced',
                'etecsaFound' => true,
                'etecsaStateCode' => 'OK',
                'message' => 'ETECSA confirma la recarga como Realizada; el estado local se corrigió de Failed a Completed.',
            ]), ['http_code' => 200]);
        });

        $result = $client->retrySale(
            $this->environment(),
            '2610080101856',
            'check',
            'El cliente reporta que no recibió la recarga',
            'Failed',
        );

        $this->assertSame([
            'environment' => 'TEST',
            'transactionId' => '2610080101856',
            'mode' => 'check',
            'reason' => 'El cliente reporta que no recibió la recarga',
            'expectedStatus' => 'Failed',
        ], $sentBody);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Completed', $result['body']['status']);
        $this->assertSame('synced', $result['body']['action']);
    }

    public function testRetrySaleOmitsExpectedStatusWhenNull(): void
    {
        $sentBody = null;
        $client = $this->client(function (string $method, string $url, array $options) use (&$sentBody) {
            $sentBody = json_decode($options['body'], true);

            return new MockResponse(json_encode(['status' => 'Pending']), ['http_code' => 200]);
        });

        $client->retrySale($this->environment(), '2610080101856', 'resend', 'motivo valido');

        $this->assertArrayNotHasKey('expectedStatus', $sentBody);
    }

    public function testRetrySaleReadsBodyOn409WithoutThrowing(): void
    {
        $client = $this->client(function () {
            return new MockResponse(json_encode([
                'error' => 'El estado no permite el modo pedido',
            ]), ['http_code' => 409]);
        });

        $result = $client->retrySale($this->environment(), '2610080101856', 'resend', 'motivo valido');

        $this->assertSame(409, $result['status']);
        $this->assertSame('El estado no permite el modo pedido', $result['body']['error']);
    }

    public function testRetrySaleReadsBodyOn404WithoutThrowing(): void
    {
        $client = $this->client(function () {
            return new MockResponse(json_encode(['error' => 'not found']), ['http_code' => 404]);
        });

        $result = $client->retrySale($this->environment(), 'does-not-exist', 'check', 'motivo valido');

        $this->assertSame(404, $result['status']);
    }

    public function testRetrySaleWrapsTransportFailureAsGatewayTimeout(): void
    {
        $client = $this->client(function () {
            return new MockResponse('', ['error' => 'Connection refused']);
        });

        $this->expectException(MyCurrentException::class);

        $client->retrySale($this->environment(), '2610080101856', 'check', 'motivo valido');
    }
}
