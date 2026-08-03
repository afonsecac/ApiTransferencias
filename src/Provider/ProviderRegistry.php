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
 *
 * El indexado es perezoso a propósito (se construye en el primer get()/
 * has()/registered(), no en el constructor): ProviderCredentialsResolver
 * depende de este registro para resolver getConfigSchema(), y los clientes
 * HTTP de los propios proveedores (EtecsaGatewayClient, DTOneHttpClient,
 * CsqHttpClient) dependen de ese resolver — indexar en el constructor
 * forzaría instanciar los 3 proveedores ANTES de que este registro termine
 * de construirse, formando un ciclo real que agota memoria (ver
 * RewindableGenerator). Al diferir la iteración, el registro ya existe
 * (aunque vacío) cuando el resolver lo recibe, y el índice solo se arma la
 * primera vez que alguien pide un proveedor en tiempo de ejecución — para
 * entonces todo el grafo de servicios ya terminó de construirse.
 */
final class ProviderRegistry
{
    /** @var array<string, CommunicationProviderInterface>|null caché perezosa del índice — null hasta el primer index() */
    private ?array $indexedProviders = null;

    /**
     * @param iterable<CommunicationProviderInterface> $providerIterator
     */
    public function __construct(
        #[AutowireIterator('app.communication_provider')]
        private readonly iterable $providerIterator,
    ) {
    }

    public function has(CommunicationProviderEnum $code): bool
    {
        return isset($this->index()[$code->value]);
    }

    public function get(CommunicationProviderEnum $code): CommunicationProviderInterface
    {
        return $this->index()[$code->value]
            ?? throw new MyCurrentException(
                'PROVIDER_NOT_REGISTERED',
                "El proveedor {$code->value} no está registrado",
                500,
            );
    }

    /**
     * @return array<string, CommunicationProviderInterface>
     */
    private function index(): array
    {
        if ($this->indexedProviders === null) {
            $this->indexedProviders = [];
            foreach ($this->providerIterator as $provider) {
                $this->indexedProviders[$provider->getCode()->value] = $provider;
            }
        }

        return $this->indexedProviders;
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
            array_keys($this->index()),
        );
    }
}
