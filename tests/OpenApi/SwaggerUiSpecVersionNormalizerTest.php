<?php

namespace App\Tests\OpenApi;

use App\OpenApi\SwaggerUiSpecVersionNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SwaggerUiSpecVersionNormalizerTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $received = [];

    private function normalizer(): SwaggerUiSpecVersionNormalizer
    {
        $inner = new class($this->received) implements NormalizerInterface {
            public function __construct(private array &$received) {}

            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                $this->received[] = $context;

                return ['openapi' => $context['spec_version'] ?? '3.2.0'];
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => true];
            }
        };

        return new SwaggerUiSpecVersionNormalizer($inner);
    }

    public function testLaLlamadaDeSwaggerUiSinContextoSeRebajaA30(): void
    {
        $result = $this->normalizer()->normalize(new \stdClass(), 'json', []);

        $this->assertSame('3.0.0', $result['openapi']);
    }

    public function testRespetaLaVersionPedidaExplicitamente(): void
    {
        $normalizer = $this->normalizer();

        $this->assertSame('3.2.0', $normalizer->normalize(new \stdClass(), 'json', ['spec_version' => '3.2.0'])['openapi']);
        $normalizer->normalize(new \stdClass(), 'json', ['spec_version' => '']);
        $this->assertSame('', $this->received[1]['spec_version']);
    }

    public function testNoTocaLasPeticionesDeLaDefinicionConContexto(): void
    {
        $result = $this->normalizer()->normalize(new \stdClass(), 'json', ['base_url' => '']);

        $this->assertSame('3.2.0', $result['openapi']);
    }
}
