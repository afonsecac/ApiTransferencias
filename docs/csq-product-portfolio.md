# CSQ — Catálogo de productos (`GET /product/portfolio`)

> **Estado (2026-08-10):** `CsqCommunicationProvider` implementa
> `ProviderCatalogInterface::fetchProducts()` contra este endpoint, para
> productos de Cuba con `amountType=by_list` **y** `by_range` (Cubacel y
> Nauta CUP ya se sincronizan — ver
> [`amountType`: `by_list` vs `by_range`](#amounttype-by_list-vs-by_range)
> para la corrección de diseño del 2026-08-10 y cómo se expande el rango).

## Endpoint

- **Método:** `GET`
- **Path completo:** `https://evsb.csqworld.com/product/portfolio`
- **Headers:** los mismos `U`/`ST`/`SH` documentados para el resto de la API
  (ver `CsqHttpClient::buildAuthHeaders()`), `Accept: application/json` (no
  `Accept: json` — ver nota de bug abajo).
- **Sin parámetros de path ni query** — confirmado contra la doc pública y
  contra el servidor real (curl, 2026-08-09). Devuelve el catálogo mundial
  completo de CSQ en una sola respuesta, no paginada.

## Forma de la respuesta

Una lista de **un solo elemento** con el `terminalId` de la cuenta y el
array completo de productos:

```json
[
  {
    "terminalId": 173103,
    "products": [
      {
        "articleId": 7951,
        "name": "Cubacel  Pack Combos",
        "countryId": 192,
        "country": "Cuba",
        "topupType": "Bundles",
        "mask": "53########",
        "amountType": "by_list",
        "saleAmount": { "from": null, "to": null, "step": null, "list": [2200, 3300, 4400] },
        "exchangeRate": 22.0,
        "saleCurrency": "USD",
        "destinationCurrency": "CUP",
        "discount": null,
        "region": null,
        "serviceFee": 0,
        "productDescription": "Recibe Combos de Recargas, Datos, Minutos y SMS en Cuba ..."
      },
      {
        "articleId": 7854,
        "name": "Cubacel",
        "countryId": 192,
        "country": "Cuba",
        "topupType": "RTR",
        "amountType": "by_range",
        "saleAmount": { "from": 1100, "to": 5500, "step": 1, "list": [] },
        "exchangeRate": 22.0,
        "saleCurrency": "USD",
        "destinationCurrency": "CUP"
      }
    ]
  }
]
```

## `amountType`: `by_list` vs `by_range`

CSQ no tiene un precio único por producto — cada `articleId` declara cómo se
determina el monto de destino:

| `amountType` | Significado | Campo relevante |
|---|---|---|
| `by_list` | Lista fija y cerrada de montos vendibles | `saleAmount.list` (array de montos, en `destinationCurrency`) |
| `by_range` | El monto se elige libremente dentro de un rango | `saleAmount.from`/`to`/`step` |

Del portfolio real de Cuba (2026-08-09), de los 3 productos:

| `articleId` | `name` | `topupType` | `amountType` | Sincronizado |
|---|---|---|---|:---:|
| 7951 | Cubacel Pack Combos | Bundles | `by_list` `[2200, 3300, 4400]` | ✅ |
| 7854 | Cubacel | RTR | `by_range` `1100–5500 step 1` | ✅ |
| 7855 | Nauta CUP | Data | `by_range` `1000–6000 step 100` | ✅ |

## Mapeo a `ProviderProductDto`

Decisión explícita del usuario (2026-08-09): **un producto CSQ (`by_list` o
`by_range`) con N montos se expande a N `CommunicationProduct`**, uno por
monto — no hay concepto de "precio variable" en `CommunicationProduct`
(columna `price` escalar), así que cada denominación es su propia fila de
catálogo, igual que si fueran productos distintos.

- `externalId` = `"{articleId}-{amount}"` (p.ej. `"7951-2200"`) — la clave de
  upsert de `CommunicationCatalogSyncService` es
  `(environment, provider, externalRef)`, así que cada denominación necesita
  un `externalRef` propio.
- `name` = `"{name} - {amount} {destinationCurrency}"` para poder distinguir
  las N filas en el catálogo del dashboard.
- `wholesalePrice` (USD) = `round(amount / exchangeRate, 2)` — `amount` está
  en `destinationCurrency` (p.ej. CUP), `exchangeRate` es el tipo de cambio
  CSQ hacia `saleCurrency` (USD). Un producto con `exchangeRate` nulo o ≤ 0
  se omite (no se puede calcular un precio válido) con log de advertencia.
- `destinationAmount`/`destinationUnit` = `amount`/`destinationCurrency`.
- `priceCurrency` = `saleCurrency`.
- `service`/`isMobileOrInternetService` se derivan de `topupType` (ver
  `CsqCommunicationProvider::TOPUP_TYPE_SERVICE_MAP`), con el mismo criterio
  que `DTOneCommunicationProvider` usa para Nauta
  (`service=Utilities`/`subservice=Internet`): `RTR`→Mobile/Airtime,
  `Bundles`→Mobile/Bundle, `Data`→Utilities/Internet. Cualquier otro
  `topupType` (Gift Cards, Pinless, etc.) no se clasifica como servicio móvil
  o de Internet.

### De dónde sale `amount` según `amountType`

- **`by_list`**: `amount` es cada valor de `saleAmount.list`, **ya en
  `destinationCurrency`, sin conversión** — verificado en vivo: 2200 CUP /
  exchangeRate 22 = $100 USD exacto.
- **`by_range`** (corrección de diseño, 2026-08-10): `saleAmount.from`/`to`
  **NO están en `destinationCurrency`** — hay que aplicar
  `realAmount = (valor / 100) * exchangeRate` para obtener el monto real.
  Esto se descubrió porque una compra real de prueba (250 CUP) funcionó
  contra Cubacel pese a estar "fuera" del rango 1100–5500 crudo asumido
  antes — el rango real es `(1100/100)*22=242` a `(5500/100)*22=1210` CUP, y
  250 sí cae ahí. Con el rango real ya convertido, se generan los múltiplos
  del step de catálogo propio — NO el step nativo de CSQ, que para Cubacel
  es casi continuo: `(1/100)*22=0.22` CUP — dentro de `[realFrom, realTo]`,
  opcionalmente acotado por límites min/max. Un producto cuyo rango
  resultante sea más angosto que un solo step de catálogo se omite con log
  de advertencia (ningún punto cabría). La compra real de 250 CUP no
  estaba alineada a un múltiplo exacto del step nativo de ningún producto y
  aun así se aceptó — sugiere que CSQ no valida alineación estricta al
  step, solo que el monto caiga dentro del rango; si algún monto generado
  llegara a rechazarse en la práctica, revisar este supuesto.

**Configuración (2026-08-10):** el step de catálogo y los límites min/max
son configurables por proveedor en `config/services.yaml`, sin tocar
código — inyectados en `CsqCommunicationProvider` vía
`#[Autowire(param: ...)]`:

| Parámetro | Default | Efecto |
|---|---|---|
| `app.csq.catalog_step_cup` | `25.0` | Separación en CUP entre productos generados |
| `app.csq.catalog_min_amount_cup` | `~` (sin límite) | Recorta `realFrom` hacia arriba si está seteado |
| `app.csq.catalog_max_amount_cup` | `~` (sin límite) | Recorta `realTo` hacia abajo si está seteado |

## Filtro de país

Igual que `DTOneCommunicationProvider::COUNTRY_ISO_CODE`, el negocio solo
vende Cuba: `fetchProducts()` descarta cualquier producto con
`countryId !== 192`. A diferencia de DTOne, el endpoint de CSQ no admite
filtrar por país en origen (no hay parámetro de query) — el filtro es
siempre del lado del cliente, sobre la respuesta completa. Esto es aceptable
porque el portfolio completo de CSQ es pequeño (decenas de productos, no
decenas de miles como el catálogo mundial de DTOne).

## Alcance actual

Cubacel y Nauta CUP — los dos servicios principales que este negocio vende
en Cuba — ya se sincronizan (`by_range`, ver arriba). Cada uno se expande a
varios `CommunicationProduct` de precio fijo (múltiplos de
`CATALOG_STEP_CUP`), no a un único producto de monto variable — el cliente
elige entre esos montos fijos al comprar, igual que con `by_list`. No hay
ningún concepto de "monto elegido libremente en el momento de la venta" en
el flujo (`CreateSaleDto` solo acepta `packageId` fijo,
`CommunicationProduct.price` es un escalar único) — no hizo falta agregarlo:
la corrección de la fórmula de conversión (`(from|to)/100 * exchangeRate`)
fue suficiente para hacer manejable el rango real con un step de catálogo
propio, sin tocar el flujo de venta.

## Bug de header (`Accept`)

`Accept: json` (el valor "ejemplo" literal de la doc pública de CSQ) responde
`406` en los endpoints de negocio como `/product/portfolio` — hay que mandar
`Accept: application/json`. `/ping` lo tolera igual (por eso el bug no se
detectó antes). Corregido en `CsqHttpClient::buildAuthHeaders()`
(2026-08-09).
