<?php

namespace App\Service\Pricing;

/**
 * De dónde salió el precio/visibilidad que devuelve PackageCatalogResolver
 * (Fase 2) para un CommunicationPackage — análogo a PriceSourceEnum pero
 * para el catálogo agnóstico de proveedor nuevo. Usado como badge en el
 * dashboard y para decidir si un paquete se muestra o se oculta.
 */
enum PackageOfferSourceEnum: string
{
    /** Contrato propio del tenant, vigente (máxima prioridad). */
    case TENANT_CONTRACT = 'TENANT_CONTRACT';

    /** Contrato "por defecto" (tenant IS NULL), vigente, sin contrato propio. */
    case DEFAULT_CONTRACT = 'DEFAULT_CONTRACT';

    /** Promoción vigente para esta tupla (congela su propio precio). */
    case PROMOTION = 'PROMOTION';

    /** Sin contrato ni promoción: MAX(price) entre proveedores + margen. */
    case PRODUCT_MAX = 'PRODUCT_MAX';

    /** Visible por contrato pero sin ningún proveedor que cubra la tupla, o no visible en absoluto. */
    case UNAVAILABLE = 'UNAVAILABLE';
}
