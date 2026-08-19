<?php

namespace App\Service\Pricing;

/**
 * De dónde salió el precio que devuelve PackageSalePriceResolver — usado
 * como badge en el dashboard ("CONTRATO"/"PROVEEDOR") y para distinguir un
 * precio de negocio real de un snapshot legacy pre-backfill.
 */
enum PriceSourceEnum: string
{
    /** priceClientPackage (contrato congelado) o un CommunicationPricePackage con isContract=true vigente. */
    case CONTRACT = 'CONTRACT';

    /** Sin contrato: costo del CommunicationProduct convertido vía ProductPriceResolver. */
    case PRODUCT = 'PRODUCT';

    /** Ni contrato ni producto resoluble (fila legacy anterior al backfill de referencias). */
    case LEGACY_SNAPSHOT = 'LEGACY_SNAPSHOT';
}
