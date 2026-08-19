<?php

namespace App\Service\Provider;

use App\Entity\CommunicationProduct;

/**
 * Extraído de ClientCatalogImportService::matchesSaleType() (V2 Fase 1) para
 * que ProviderDispatchResolver (Fase 2) use el MISMO criterio al buscar un
 * producto dispatchable por tupla — un `saleType` fijo ('recharge'/'sale')
 * solo debe considerar los productos que de verdad se pueden vender bajo ese
 * modo, y no puede haber dos implementaciones de este criterio divergiendo.
 */
final class ProductSaleTypeMatcher
{
    /**
     * `saleType === null` significa que aplica a ambos modos, así que no se
     * filtra nada.
     *
     * "Elegible para recarga" exige DOS condiciones a la vez:
     *  - mismo criterio que CommunicationSaleService::assertRechargeableProduct():
     *    todo lo que sea PIN_PURCHASE se vende siempre como paquete, nunca
     *    como recarga.
     *  - CommunicationProduct::isMobileOrInternetService(): "recharge" es
     *    solo telefonía móvil o Internet (decisión de negocio) — todo lo
     *    demás (gift cards, comida, SIM/equipos físicos...) es 'sale' aunque
     *    el proveedor lo entregue como si fuera una recarga directa.
     */
    public function matches(CommunicationProduct $product, ?string $saleType): bool
    {
        if ($saleType === null) {
            return true;
        }

        $isPinPurchase = str_contains($product->getPackageType() ?? '', 'PIN_PURCHASE');
        $rechargeEligible = !$isPinPurchase && $product->isMobileOrInternetService();

        return $saleType === 'sale' ? !$rechargeEligible : $rechargeEligible;
    }
}
