<?php

namespace App\OpenApi;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * La definición OpenAPI sale como 3.2.0 y el Swagger UI que trae API Platform no la entiende («Unable to render this
 * definition»). SwaggerUiProcessor normaliza la definición con un contexto vacío (sin `spec_version`), así que esa llamada
 * se rebaja a 3.0.0. Las peticiones de la definición (JSON, JSON-LD…) siempre llevan `spec_version` en su contexto, aunque
 * sea vacío, y siguen saliendo en 3.2.0 salvo que el cliente pida otra con `?spec_version=`.
 */
#[AsDecorator(decorates: 'api_platform.openapi.normalizer.legacy', priority: -3)]
final class SwaggerUiSpecVersionNormalizer implements NormalizerInterface
{
    public function __construct(private readonly NormalizerInterface $inner) {}

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if ([] === $context) {
            $context['spec_version'] = '3.0.0';
        }

        return $this->inner->normalize($data, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }
}
