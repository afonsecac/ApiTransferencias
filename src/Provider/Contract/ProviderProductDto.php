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
        /**
         * true si el producto es servicio de telefonía móvil o Internet
         * (lo único que aplica a un enrutado con `saleType='recharge'` — ver
         * ClientCatalogImportService::matchesSaleType()); false para
         * cualquier otra cosa (gift cards, comida, SIM/equipos físicos...).
         * Cada adaptador de proveedor decide esto con su propio vocabulario
         * (para DTOne: campos `service`/`subservice` — ver
         * DTOneCommunicationProvider::fetchProducts()). ETECSA es
         * exclusivamente telefonía móvil, así que siempre es true ahí.
         */
        public bool $isMobileOrInternetService,
        /**
         * Forma `{name, subservice: {name}}` — mismo shape que
         * CommunicationClientPackage::$service, para que
         * ClientCatalogImportService pueda crear el CommunicationClientPackage
         * automáticamente sin tener que re-derivar nada. ETECSA (solo
         * telefonía móvil) siempre usa el mismo valor fijo.
         *
         * @var array{name?: string, subservice?: array{name?: string}}
         */
        public array $service,
        /**
         * Identificador(es) de destino que el proveedor exige para
         * despachar ESTE producto — no siempre es un número de teléfono
         * (ver docs/dtone-product-types.md y CommunicationSaleService::
         * assertRecipientIdentifierSatisfied()). Lista de opciones
         * alternativas (OR), cada una una lista de campos que deben venir
         * TODOS juntos (AND), usando el vocabulario neutral de
         * CommunicationSaleRecharge: 'phoneNumber' | 'accountIdentifier'.
         * `[]` significa "el proveedor no lo declara" — se interpreta como
         * el comportamiento histórico (exigir solo phoneNumber). Cada
         * adaptador traduce el vocabulario propio del proveedor aquí
         * (DTOne: mobile_number/account_number — ver
         * DTOneCommunicationProvider::mapRequiredIdentifierFields()).
         *
         * @var list<list<string>>
         */
        public array $requiredIdentifierFields = [],
    ) {
    }
}
