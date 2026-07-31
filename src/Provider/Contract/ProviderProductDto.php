<?php

namespace App\Provider\Contract;

/**
 * Producto de catálogo neutral. Modelado sobre la forma de producto de DT One
 * (destination/benefits/validity) porque los campos JSON de presentación de
 * CommunicationClientPackage ya replican ese esquema — así el mapeo
 * DTOne -> cliente es casi identidad.
 */
final readonly class ProviderProductDto
{
    /**
     * @param array<int, array<string, mixed>> $benefits
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $description,
        public ?string $productTypeRaw,
        public float $wholesalePrice,
        public ?string $priceCurrency,
        public ?float $destinationAmount,
        public ?float $destinationMinAmount,
        public ?float $destinationMaxAmount,
        public ?string $destinationUnit,
        public array $benefits,
        public bool $enabled,
        public ?\DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validTo,
        public array $raw,
    ) {
    }
}
