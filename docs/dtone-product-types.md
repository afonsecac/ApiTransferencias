# DT One — Tipos de producto

> **Nota (2026-08-02):** la documentación pública de DT One usa el nombre
> `product_type`, pero el payload real de `GET /v1/products` en el sandbox
> devuelve este clasificador en el campo **`type`** (a nivel raíz del
> producto), no en `product_type` — ese campo no existe en la respuesta real.
> No confundir con el `type` anidado dentro de cada elemento de `benefits[]`
> (p.ej. `"CREDITS"`), que es un campo distinto con otro significado. El
> código (`DTOneCommunicationProvider::fetchProducts()`) lee `$item['type']`
> por esto — verificado contra un payload crudo real, no contra la doc.

## Visión general

DT One clasifica sus productos con el campo `product_type` (según su documentación pública; ver la nota de arriba sobre el nombre real en la respuesta), que combina dos dimensiones:

| Dimensión | Valores |
|-----------|---------|
| **Monto** | `FIXED_VALUE` — monto fijo predeterminado |
| | `RANGED_VALUE` — el remitente elige un monto dentro de un rango |
| **Mecanismo de entrega** | `RECHARGE` — recarga directa e instantánea en el número del beneficiario |
| | `PIN_PURCHASE` — se genera un código PIN/voucher que el beneficiario canjea manualmente |

Combinando ambas dimensiones resultan los cuatro tipos:

---

## 1. `FIXED_VALUE_RECHARGE`

**Recarga directa de valor fijo.**

El monto está predeterminado por el operador (por ejemplo: 5 USD, 10 USD, 25 USD). Al completarse la transacción, DT One carga ese valor directamente en el número de teléfono del beneficiario sin ninguna acción adicional por su parte.

**Campos clave del producto:**
```json
{
  "product_type": "FIXED_VALUE_RECHARGE",
  "destination": {
    "amount": 250.00,
    "unit": "CUP"
  },
  "prices": {
    "wholesale": { "amount": 4.50, "unit": "USD" },
    "retail":    { "amount": 5.00, "unit": "USD" }
  },
  "benefits": [
    { "type": "TALKTIME", "base_amount": 250.00, "promo_amount": 300.00 }
  ]
}
```

**La transacción devuelve:** confirmación de recarga + referencia del operador + beneficios aplicados.

**Uso típico:** recargas de Cubacel, airtime de cualquier operador prepago.

---

## 2. `RANGED_VALUE_RECHARGE`

**Recarga directa de valor variable.**

Igual que el anterior (recarga instantánea en el número), pero el monto no está fijo: el remitente elige cualquier valor dentro de un rango mínimo–máximo que define el operador.

**Campos clave del producto:**
```json
{
  "product_type": "RANGED_VALUE_RECHARGE",
  "destination": {
    "min_amount": 100.00,
    "max_amount": 1250.00,
    "unit": "CUP"
  },
  "prices": {
    "wholesale": { "min_amount": 1.90, "max_amount": 23.75, "unit": "USD" },
    "retail":    { "min_amount": 2.00, "max_amount": 25.00, "unit": "USD" }
  }
}
```

**La transacción devuelve:** lo mismo que FIXED_VALUE_RECHARGE más el campo `amount_sent` con el monto exacto enviado.

**Uso típico:** operadores que permiten recargas de importe libre (el agente teclea la cantidad).

---

## 3. `FIXED_VALUE_PIN_PURCHASE`

**Código PIN/voucher de valor fijo.**

El monto es fijo, pero la entrega NO es una recarga directa: DT One genera un código PIN y un número de serie que el beneficiario debe introducir manualmente en su teléfono o plataforma para canjear el crédito.

**Campos clave del producto:**
```json
{
  "product_type": "FIXED_VALUE_PIN_PURCHASE",
  "destination": {
    "amount": 10.00,
    "unit": "USD"
  },
  "prices": {
    "wholesale": { "amount": 9.50, "unit": "USD" },
    "retail":    { "amount": 10.00, "unit": "USD" }
  }
}
```

**La transacción devuelve:**
```json
{
  "pin": {
    "code": "1234-5678-9012-3456",
    "serial_number": "SN987654321"
  }
}
```

**Uso típico:** tarjetas de recarga virtuales, gift cards de telefonía, PINs de juegos (gaming credits).

---

## 4. `RANGED_VALUE_PIN_PURCHASE`

**Código PIN/voucher de valor variable.**

Combina la flexibilidad del rango con la entrega por PIN. El remitente elige el monto dentro del rango permitido y el sistema genera un PIN con esa denominación específica.

**Campos clave del producto:**
```json
{
  "product_type": "RANGED_VALUE_PIN_PURCHASE",
  "destination": {
    "min_amount": 5.00,
    "max_amount": 100.00,
    "unit": "USD"
  },
  "prices": {
    "wholesale": { "min_amount": 4.75, "max_amount": 95.00, "unit": "USD" },
    "retail":    { "min_amount": 5.00, "max_amount": 100.00, "unit": "USD" }
  }
}
```

**La transacción devuelve:** igual que FIXED_VALUE_PIN_PURCHASE más el campo `denomination` con el monto exacto del PIN emitido.

**Uso típico:** gift cards corporativas de importe personalizable, vouchers prepago de valor variable.

---

## Tabla comparativa

| | FIXED_VALUE_RECHARGE | RANGED_VALUE_RECHARGE | FIXED_VALUE_PIN_PURCHASE | RANGED_VALUE_PIN_PURCHASE |
|---|:---:|:---:|:---:|:---:|
| **Monto** | Fijo | Variable (min–max) | Fijo | Variable (min–max) |
| **Entrega** | Directa al número | Directa al número | Código PIN | Código PIN |
| **Acción del beneficiario** | Ninguna | Ninguna | Canjear el PIN | Canjear el PIN |
| **Campo resultado clave** | `benefits` | `benefits` + `amount_sent` | `pin.code` + `pin.serial_number` | `pin.code` + `denomination` |

---

## Identificación de tarjetas de regalo

Las gift cards no se distinguen por el `product_type` sino por el **`service_id`**. Para obtener solo tarjetas de regalo:

```
GET /v1/products?service_id=4
GET /v1/products?service_id=4&country_iso_code=US
GET /v1/products?service_id=4&subservice_id=42
```

### Categorías (`subservice_id`)

| `subservice_id` | Categoría | Ejemplos |
|-----------------|-----------|---------|
| `41` | Retail | Amazon, iTunes, Google Play |
| `42` | Gaming | Steam, Xbox, PlayStation |
| `43` | Cash Cards | Visa prepago, Mastercard prepago |
| `44` | Food | Uber Eats, Starbucks |
| `45` | Entertainment | Netflix, Spotify |
| `46` | Travel & Transport | Airbnb, Booking |

### `product_type` en gift cards

Dentro del `service_id = 4`, el `product_type` sigue indicando el mecanismo de entrega:

- **`FIXED_VALUE_PIN_PURCHASE`** — el más común; valor fijo entregado como código (Amazon $25, iTunes $10…)
- **`RANGED_VALUE_PIN_PURCHASE`** — valor personalizable entregado como código
- **`FIXED_VALUE_RECHARGE`** — acreditación directa en la cuenta del beneficiario (poco frecuente en gift cards)
- **`RANGED_VALUE_RECHARGE`** — igual pero con monto variable

---

## Campo de precio a usar

Para formar los precios de la plataforma, el campo relevante es siempre **`prices.wholesale.amount`** — es el coste real que DT One carga en la wallet del integrador.

`prices.retail.amount` es el precio de referencia sugerido al usuario final, pero no es obligatorio respetarlo.
