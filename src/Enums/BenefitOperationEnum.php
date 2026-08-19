<?php

namespace App\Enums;

/**
 * Cómo se calcula un beneficio de promoción (CommunicationPackage::$benefits)
 * respecto a su línea base — el paquete regular equivalente (misma tupla
 * monto/moneda, sin promoción) para beneficios no ligados al monto de
 * destino, o el propio destinationAmount para CREDITS/CURRENCY. Ver
 * CommunicationPackageAdminService::applyBenefitOperations().
 */
enum BenefitOperationEnum: string
{
    /** total = base × value (ej. "sextuplica" → value: 6). */
    case MULTIPLY = 'MULTIPLY';

    /** total = base + value (ej. bonifica +20GB → value: 20). */
    case ADD = 'ADD';

    /** total = value, ignora la línea base (ej. datos ilimitados). */
    case SET = 'SET';
}
