# Esquema del `transactionId` de las ventas por subtipo

El `transactionId` de una venta (`communication_sale_info.transaction_id`, único) lo genera `CommunicationSaleService` con este formato:

```
{ymd}{prefijo de subtipo}{secuencia de 6 cifras}
```

| Parte | Origen | Ejemplo |
|---|---|---|
| `ymd` | fecha de creación (`(new \DateTime('now'))->format('ymd')`) | `261012` |
| prefijo de subtipo | `01` recarga, `02` paquete | `01` |
| secuencia | `ConfigureSequenceService::getLastSequence(<clase>)`, una secuencia por clase de venta y por año, rellenada a 6 cifras | `000042` |

Dónde se genera: `CommunicationSaleService` (recargas, dos sitios; paquetes, uno).

## Reserva para el mercado

El prefijo **`03` queda reservado para las ventas de mercado** (hito 4). Cuando exista ese subtipo:

- Usará su propia secuencia (`getLastSequence(<ClaseDeVentaDeMercado>::class)`), no la de recargas ni la de paquetes.
- Se añadirá a `App\Service\SaleSubtype` y a cada sitio que bifurca por subtipo; el resto fallará con `UNSUPPORTED_SALE_SUBTYPE` hasta entonces (ver `tests/Service/SaleSubtypeTest.php`).

No se debe reutilizar `01` ni `02` para otro subtipo: ambos prefijos, junto con la secuencia por clase, son lo que garantiza la unicidad entre subtipos dentro del mismo día.
