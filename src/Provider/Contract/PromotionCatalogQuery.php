<?php

namespace App\Provider\Contract;

/**
 * Parámetros para ProviderPromotionCatalogInterface::fetchPromotionProducts()
 * — qué tramos cubre la promoción y en qué ventana está vigente. Cada
 * adaptador decide con esto qué fuente consultar: DTOne filtra su propio
 * `GET /v1/promotions` por solapamiento de fecha; ETECSA/CSQ, sin concepto
 * de "promoción" del lado del proveedor, ignoran la ventana y devuelven su
 * catálogo normal (la vigencia la impone nuestro CommunicationPackage, no
 * el proveedor).
 */
final readonly class PromotionCatalogQuery
{
    /**
     * @param list<float> $destinationAmounts montos exactos de los tramos generados
     */
    public function __construct(
        public string $destinationCurrency,
        public array $destinationAmounts,
        public \DateTimeImmutable $activeFrom,
        public \DateTimeImmutable $activeTo,
    ) {
    }
}
