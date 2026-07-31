<?php

namespace App\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Descubre en runtime todos los adaptadores de proveedor registrados (tag
 * 'app.communication_provider', ver _instanceof en config/services.yaml) y
 * los indexa por su código. Un proveedor mal etiquetado o con getCode()
 * duplicado falla en el primer arranque del contenedor, no en producción.
 */
final class ProviderRegistry
{
    /** @var array<string, CommunicationProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<CommunicationProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.communication_provider')]
        iterable $providers,
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->getCode()->value] = $provider;
        }
    }

    public function has(CommunicationProviderEnum $code): bool
    {
        return isset($this->providers[$code->value]);
    }

    public function get(CommunicationProviderEnum $code): CommunicationProviderInterface
    {
        return $this->providers[$code->value]
            ?? throw new MyCurrentException(
                'PROVIDER_NOT_REGISTERED',
                "El proveedor {$code->value} no está registrado",
                500,
            );
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return T
     */
    public function getFor(CommunicationProviderEnum $code, string $interface): object
    {
        $provider = $this->get($code);

        if (!$provider instanceof $interface) {
            throw new MyCurrentException(
                'PROVIDER_CAPABILITY_UNSUPPORTED',
                "El proveedor {$code->value} no soporta esta operación ({$interface})",
                501,
            );
        }

        return $provider;
    }

    /**
     * @return list<CommunicationProviderEnum>
     */
    public function registered(): array
    {
        return array_map(
            static fn (string $code) => CommunicationProviderEnum::from($code),
            array_keys($this->providers),
        );
    }
}
