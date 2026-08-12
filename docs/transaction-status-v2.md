# Sobre homologado de `transactionStatus` (v2)

## Por qué existe

Antes de este cambio, `CommunicationSaleInfo::$transactionStatus` (columna JSON `transaction_status`) guardaba la respuesta **cruda** de cada proveedor de comunicaciones (ETECSA, DTOne, CSQ), con forma completamente distinta por proveedor, más ~4 formas adicionales que el propio backend armaba a mano para sus propios fallos internos (saldo insuficiente, usuario inesperado, timeouts...). En total había 14 sitios de escritura, todos en `App\Service\CommunicationSaleService`, con al menos 7 formas de JSON distintas para la misma columna.

`App\Provider\TransactionStatus` (`src/Provider/TransactionStatus.php`) define un único sobre para todos esos sitios, sin importar el proveedor ni si el resultado vino de un round-trip real o de una validación nuestra.

## El sobre

```php
[
    'schemaVersion'     => 2,               // int, SIEMPRE presente en escrituras nuevas
    'source'            => 'provider',      // 'provider' | 'internal'
    'outcome'           => 'REJECTED',      // ProviderOutcomeEnum::value
    'provider'          => 'CSQ',           // ?string
    'providerReference' => '1786346034143', // ?string
    'providerCode'      => '927',           // ?string, sin prefijo "COM"
    'message'           => 'Insufficient funds',
    'occurredAt'        => '2026-08-12T10:33:01+00:00', // ATOM
    'raw'               => [ /* respuesta cruda del proveedor, [] si source=internal */ ],
    'context'           => [ /* opcional */ ],
    'retry'             => [ /* opcional, solo en reintentos de checkStatusOrder() */ ],
]
```

- **`source: 'provider'`** — el resultado vino de una llamada real al proveedor (`ProviderDispatchResult`/`ProviderStatusResult`). `raw` trae la respuesta cruda tal cual.
- **`source: 'internal'`** — no hubo round-trip al proveedor (validación nuestra: saldo, usuario/entorno inesperado, duplicado, excepción de persistencia...). `raw` siempre `[]`.
- **Versionado**: entero, nunca string. **v1 = ausencia de la clave `schemaVersion`** — nunca se escribe `schemaVersion: 1` ni `null`. Las ~5060 filas históricas de ETECSA (2 años) se quedan en v1 para siempre; no se reescribe histórico. Cualquier lector debe soportar v1 y v2 indistintamente.

## Códigos `INTERNAL_*`

| Código | Sitio (en `CommunicationSaleService`) |
|---|---|
| `INTERNAL_PROMOTION_EXPIRED` | `activateReservedSales()` — promoción vencida antes de activar |
| `INTERNAL_UNEXPECTED_USER` | Tenant no es `Account` |
| `INTERNAL_PRICE_UNRESOLVED` | Precio del paquete no se pudo resolver |
| `INTERNAL_INSUFFICIENT_BALANCE` | Saldo del cliente insuficiente (trae `context: {balance, required}`) |
| `INTERNAL_UNEXPECTED_ENVIRONMENT` | Environment nulo |
| `INTERNAL_UNEXPECTED_ERROR` | Catch genérico tras el dispatch de una recarga |
| `INTERNAL_DUPLICATE_TRANSACTION` | `UniqueConstraintViolationException` (transacción duplicada del cliente) |
| `INTERNAL_SALE_PRECONDITION` | Default de `failSale()` — ver códigos específicos abajo |
| `INTERNAL_UNEXPECTED_USER`, `INTERNAL_MISSING_COMMERCIAL_OFFICE`, `INTERNAL_MISSING_NATIONALITY`, `INTERNAL_MISSING_PACKAGE` | Precondiciones de `executeNewSaleInfo()` antes del dispatch de venta de paquete |
| `INTERNAL_GATEWAY_NOT_FOUND_RETRY` | 404 en `checkStatusOrder()`, con reintentos disponibles (`retry.count` < 3, ventana de 4h) |
| `INTERNAL_GATEWAY_MISSING` | 404 en `checkStatusOrder()`, sin más reintentos |
| `INTERNAL_PROVIDER_HTTP_400` | 400 durante el polling de estado |
| `INTERNAL_STATUS_QUERY_ERROR` | Catch genérico en `checkStatusOrder()` (solo va al histórico, nunca a `transactionStatus`) |

## Lectura (compatible v1/v2)

Usar siempre los métodos estáticos de lectura de `TransactionStatus`, nunca acceder a las claves a mano:

- `TransactionStatus::isV2(array $status): bool`
- `TransactionStatus::rawOf(array $status): array` — para v1, el array entero YA ES la respuesta cruda.
- `TransactionStatus::outcomeOf(array $status): ?ProviderOutcomeEnum` — `null` para v1.
- `TransactionStatus::retryCountOf(array $status): int` / `lastRetryAtOf(array $status): ?string` — **doble fallback obligatorio** (`retry.count` nuevo, `retryCount` legacy en la raíz). Perder este fallback reinicia el contador de reintentos a 0 y puede causar reenvíos duplicados.

## Regla para nuevo código

Cualquier escritura nueva de `transactionStatus` (o del `info` de `CommunicationSaleHistory`) debe pasar por `TransactionStatus::fromDispatch()` / `fromStatus()` / `internal()` / `internalPreserving()` / `withRetry()` — nunca un array literal a mano ni `$dispatchResult->raw`/`$statusResult->raw` directo. Verificación mecánica:

```sh
grep -n "setTransactionStatus(" src/Service/CommunicationSaleService.php
```

Todas las líneas deben resolver a `TransactionStatus::...` (directo o vía una variable asignada desde ahí).

## Gotcha: `claimForCompleting()` y el orden de escritura

`claimForCompleting()` (usado en la rama COMPLETED de `invokeRechargeCommunication()` y de `checkStatusOrder()`) hace un `UPDATE` SQL crudo sobre `state`/`state_process` y, si gana la carrera, llama a `$this->em->refresh($sale)`. `refresh()` **descarta cualquier cambio en memoria hecho antes de esa llamada** — incluido un `setTransactionStatus()`/`setTransactionOrder()` previo, que se pierde silenciosamente en el siguiente `flush()` sin lanzar ningún error (el histórico queda bien porque `createHistoricalCommunication()` usa la variable `$dispatchEnvelope`/`$statusEnvelope` directamente, no la entidad).

Por eso `setTransactionStatus()`/`setTransactionOrder()` en la rama COMPLETED se fijan **después** de llamar a `claimForCompleting()`, nunca antes. Este bug es preexistente (no lo introdujo la homologación) y afectaba a toda venta COMPLETED, en ambos providers vía `checkStatusOrder()` y en CSQ (síncrono) vía `invokeRechargeCommunication()` — la propia venta se quedaba con `transaction_status` desactualizado aunque el histórico sí tuviera el dato correcto.

## Fuera de alcance

`src/Service/CommunicationInfoService.php` — su `$statusResult->raw` se devuelve tal cual a `AdminInformationController` (contrato de API admin externa) y lo assertan tests existentes. Homologarlo queda para una fase aparte.
