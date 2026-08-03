<?php

namespace App\Provider\DTOne;

/**
 * Señal interna (nunca sale de DTOneHttpClient) de que DTOne respondió con
 * el error 1007001 (external_id ya usado) a una creación de transacción.
 * No representa un fallo: createTransaction() la captura y resuelve el
 * estado real vía findTransactionByExternalId().
 */
final class DTOneDuplicateExternalIdException extends \RuntimeException
{
}
