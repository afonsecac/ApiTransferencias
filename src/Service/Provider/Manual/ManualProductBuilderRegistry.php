<?php

namespace App\Service\Provider\Manual;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ManualProductBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Descubre en runtime los ManualProductBuilderInterface registrados (tag
 * 'app.manual_product_builder', ver _instanceof en config/services.yaml) —
 * mismo patrón perezoso que ProviderRegistry. No todo proveedor tiene un
 * builder (hoy DTOne no): has()/get() son la forma de saberlo antes de
 * intentar un alta manual.
 */
final class ManualProductBuilderRegistry
{
    /** @var array<string, ManualProductBuilderInterface>|null */
    private ?array $indexed = null;

    /**
     * @param iterable<ManualProductBuilderInterface> $builderIterator
     */
    public function __construct(
        #[AutowireIterator('app.manual_product_builder')]
        private readonly iterable $builderIterator,
    ) {
    }

    public function has(CommunicationProviderEnum $code): bool
    {
        return isset($this->index()[$code->value]);
    }

    public function get(CommunicationProviderEnum $code): ManualProductBuilderInterface
    {
        return $this->index()[$code->value]
            ?? throw new MyCurrentException(
                'MANUAL_PRODUCT_PROVIDER_UNSUPPORTED',
                "El proveedor {$code->value} no tiene alta manual de productos implementada",
                422,
            );
    }

    /**
     * @return list<CommunicationProviderEnum>
     */
    public function supported(): array
    {
        return array_map(
            static fn (string $code) => CommunicationProviderEnum::from($code),
            array_keys($this->index()),
        );
    }

    /**
     * @return array<string, ManualProductBuilderInterface>
     */
    private function index(): array
    {
        if ($this->indexed === null) {
            $this->indexed = [];
            foreach ($this->builderIterator as $builder) {
                $this->indexed[$builder->getCode()->value] = $builder;
            }
        }

        return $this->indexed;
    }
}
