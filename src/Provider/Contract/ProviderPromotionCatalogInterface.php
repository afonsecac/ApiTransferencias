<?php

namespace App\Provider\Contract;

/**
 * Capacidad opcional (ProviderRegistry::allImplementing()): "dame los
 * productos candidatos para poblar las equivalencias de una promoción V2"
 * (Fase 5C/5D del rediseño de promociones). Cada proveedor decide su propia
 * fuente — no hay un criterio único:
 *  - DTOne: GET /v1/promotions (campañas reales del proveedor, filtradas
 *    por solapamiento de ventana) — solo devuelve productos que de verdad
 *    pertenecen a una promoción vigente de DTOne, evitando el problema de
 *    productos-bono indistinguibles por tupla (ver
 *    docs/promotion-provider-routing-por-tramo.md §4).
 *  - ETECSA/CSQ: no tienen concepto de "promoción" propio — delegan en
 *    fetchProducts() (su catálogo normal); la vigencia la impone nuestro
 *    CommunicationPackage, no el proveedor.
 *
 * Un proveedor que no la implemente simplemente no participa en el
 * poblado automático (Fase 5D) — sigue pudiendo recibir un vínculo manual.
 */
interface ProviderPromotionCatalogInterface extends CommunicationProviderInterface
{
    /**
     * @return iterable<ProviderProductDto>
     */
    public function fetchPromotionProducts(ProviderContext $context, PromotionCatalogQuery $query): iterable;
}
