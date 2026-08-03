<?php

namespace App\Enums;

/**
 * Resultado normalizado de una llamada a un proveedor externo (ETECSA, DTOne, ...),
 * independiente de los códigos/estados propios de cada uno. Ver
 * App\Provider\ProviderOutcomeMapper para la traducción a CommunicationStateEnum.
 *
 * UNKNOWN es la pieza crítica: cubre timeouts, errores 5xx y de red donde NO
 * sabemos si el proveedor llegó a procesar la operación. Una venta en UNKNOWN
 * jamás debe volver a stateProcess=CREATED (eso permitiría un reenvío que
 * puede cobrar dos veces la misma operación); solo puede avanzar
 * re-consultando el estado al proveedor.
 */
enum ProviderOutcomeEnum: string
{
    case ACCEPTED = 'ACCEPTED';
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case REJECTED = 'REJECTED';
    case FAILED = 'FAILED';
    case RETRYABLE = 'RETRYABLE';
    case UNKNOWN = 'UNKNOWN';
}
