# Chequeo y descuento de saldo — diagnóstico y plan de mejora

> Investigado 2026-07-30. Fase 1 implementada el mismo día; Fases 2-4 quedan pendientes de retomar.

## Contexto

El usuario pidió revisar si el chequeo de saldo al procesar una transacción (¿el saldo del
cliente alcanza para el monto?) es efectivo, eficiente y arquitectónicamente sano, y que se
revisaran índices y optimizaciones faltantes. El disparador fue una pista real en producción:
un balance mostrado como `184.01999999992057` (artefacto típico de guardar dinero en punto
flotante).

## Cómo funcionaba (antes de la Fase 1)

```
Venta → BalanceService::balance($userId)
  → BalanceOperationRepository::getBalanceOutput()
  → SELECT SUM(total_amount) FROM balance_operation WHERE tenant_id=X AND state='COMPLETED'
  → if (saldo < monto) rechazar
  → (más tarde, tras responder ETECSA) INSERT de un BalanceOperation débito
```

No había ningún campo `balance` persistido en `Account`. El "saldo" era siempre un `SUM()`
recalculado sobre todo el histórico de `balance_operation`, en cada chequeo, sin ningún lock.

El chequeo se repetía igual en 4 sitios de `src/Service/CommunicationSaleService.php`:
`processReserve()` (línea ~223), `processRecharge()` (línea ~300),
`invokeRechargeCommunication()` (línea ~410, re-chequeo del worker asíncrono antes de llamar a
ETECSA) y `executeSale()` (línea ~637).

## Hallazgos, por severidad

### 1. 🔴 Crítico — condición de carrera, podía dejar cuentas en negativo

El chequeo (`SELECT SUM(...)`) y el descuento (`INSERT` del débito) no estaban dentro de una
misma transacción con lock. Dos ventas simultáneas de la misma cuenta podían:
1. Ambas leer el mismo saldo (ej. $10).
2. Ambas pasar `if ($balance < $monto)` con $8 cada una.
3. Ambas insertar su débito.
4. La cuenta quedaba en **-$6**, sin que nada en el código lo impidiera.

Búsqueda exhaustiva en `src/` (`pessimistic|LockMode|FOR UPDATE|beginTransaction|SERIALIZABLE`)
encontró un único uso de lock pesimista en todo el proyecto
(`src/Service/ConfigureSequenceService.php:24-57`, para generar IDs de secuencia) — el patrón
existía y se usaba en el proyecto, simplemente nunca se había aplicado al saldo. El
`RateLimiterFactory` de `CreateSaleInfoProcessor.php` limita frecuencia de requests, no evita
esto.

**→ Resuelto en la Fase 1 (ver abajo).**

### 2. 🟠 Alto — el dinero se guarda como `double precision` (float), no `numeric`

Confirmado en migraciones y entidades: `amount`, `total_amount`, `discount`, `min_balance`,
`critical_balance`, `commission`, etc. — todos `float`/`double precision`. Confirmado también a
nivel de datos reales en prod (el `184.01999999992057` que disparó esta investigación). La
aritmética flotante acumula error en cada suma, y puede hacer que comparaciones justo en el
umbral (`$balance < $monto`) den el resultado equivocado por una diferencia de centavos de
centavo.

Archivos con columnas monetarias en `float`/`double`:
- `src/Entity/BalanceOperation.php` — `amount`, `amountTax`, `discount`, `totalAmount`
- `src/Entity/Account.php` — `discount`, `commission`, `minBalance`, `criticalBalance`
- `src/Entity/CommunicationSaleInfo.php` — `amount`, `discount`, `amountTax`, `totalPrice`
- `src/Entity/Client.php` — `discountOfClient`, `minBalance`, `criticalBalance`

**→ Pendiente. No se tocó — es un cambio transversal, más grande y arriesgado que la Fase 1,
que merece su propio proyecto dedicado (migración de columnas + revisión de toda la aritmética
PHP que hoy asume `float`).**

### 3. 🟡 Medio — el saldo se recalculaba sumando *todo* el histórico, sin caché

`balance_operation.tenant_id=5` (AWS Malta Ltd) ya tenía 4,652 filas. El `SUM()` tardaba 2ms
(`EXPLAIN ANALYZE` real contra prod, `Seq Scan`, tabla de 1.5MB) — rápido porque la tabla era
chica. Pero no había ningún snapshot/columna materializada: cada chequeo de saldo, para
siempre, iba a sumar un histórico que solo crece. Escala mal con el tiempo, no con el tráfico
de hoy.

**→ Resuelto como efecto colateral de la Fase 1** (el saldo pasó a ser una columna
materializada, actualizada atómicamente — ya no hay `SUM()` en el camino caliente de chequeo).

### 4. 🟡 Medio — posible bug de correctitud aparte, sin confirmar

`getBalanceOutput()` (el `SUM()` legado) filtraba por `state = 'COMPLETED'` pero no por
`disabled_at IS NULL`, pese a que esa columna existe en `balance_operation`. Si hay operaciones
"deshabilitadas" (anuladas) que no deberían contar, podrían haberse estado sumando igual. No se
confirmó el propósito exacto de `disabled_at` antes de la Fase 1.

**→ Pendiente de verificar.** No es bloqueante para la Fase 1 porque el nuevo saldo
materializado se alimenta con backfill del `SUM()` legado una sola vez (así que hereda esta
duda del estado histórico) y de ahí en más se mantiene con `UPDATE` atómicos, no con `SUM()`
recurrente — pero vale la pena entender `disabled_at` antes de confiar ciegamente en el
backfill inicial.

## Arquitectura propuesta (4 fases)

**Fase 1 — Chequeo+descuento atómico (resuelve la condición de carrera).** Implementada.
Ver sección siguiente.

**Fase 2 — `float` → `numeric`.** Migrar las columnas monetarias a `numeric(12,2)`, actualizar
las entidades Doctrine (`type: 'decimal'`, que en PHP se maneja como `string` para no perder
precisión) y revisar toda la aritmética en PHP que hoy asume `float`. Tratar como su propio
proyecto, no mezclado con el resto.

**Fase 3 — Índices y consulta legada.** Confirmar si `disabled_at` debería filtrarse en
`getBalanceOutput()` (el `SUM()` legado, que tras la Fase 1 pasa a ser solo de lectura/
reconciliación, ya no está en el camino caliente). Si en algún momento se necesita mantener el
`SUM()` como fuente primaria en algún reporte, agregar índice compuesto `(tenant_id, state)` en
`balance_operation`.

**Fase 4 — Defensa en profundidad.** Job de reconciliación periódico (`#[AsCronTask]`, mismo
patrón que ya usa `PurgeNotificationsTask`) comparando `account_balance.balance` contra
`SUM(balance_operation.total_amount)`, para detectar drift entre el saldo materializado y el
histórico de operaciones.

---

## Fase 1 — Implementación

### Diseño

Nueva tabla `account_balance` (una fila por cuenta), con el saldo como fuente de verdad,
actualizado con un `UPDATE` condicional atómico:

```sql
UPDATE account_balance
SET balance = balance - :monto, updated_at = NOW()
WHERE account_id = :id AND balance >= :monto
```

Si `affected rows = 0`, el saldo no alcanzaba — sin necesitar ningún lock explícito, Postgres
lo resuelve a nivel de fila por su propio MVCC (dos transacciones concurrentes que intenten
actualizar la misma fila se serializan automáticamente: la segunda espera a que la primera
haga commit, y entonces ve el saldo ya descontado). `balance_operation` sigue existiendo tal
cual — sigue siendo el libro de auditoría/histórico, no se le quita nada.

`CHECK (balance >= 0)` a nivel de BD como defensa en profundidad, para que un bug de aplicación
no pueda dejar saldo negativo ni aunque falle la lógica de arriba.

### Archivos

- `migrations/VersionXXXXXXXXXXXXXX.php` — crea `account_balance`, hace backfill desde
  `SUM(balance_operation.total_amount)` agrupado por `tenant_id`, agrega el `CHECK`.
- `src/Entity/AccountBalance.php` — nueva entidad.
- `src/Repository/AccountBalanceRepository.php` — método `tryDebit(int $accountId, float $amount): bool`
  con el `UPDATE` atómico de arriba, y `credit()`/`ensureExists()` para altas de cuenta.
- `src/Service/BalanceService.php` — `balance()` pasa a leer `account_balance.balance`
  directo (sin `SUM()`); se agrega el método de descuento atómico usado por
  `createSaleBalance()`.
- `src/Service/CommunicationSaleService.php` — los 4 puntos de chequeo pasan a usar
  chequeo+descuento atómico en vez de "leer, comparar en PHP, insertar aparte".

### Verificación

- Backfill: `SELECT account_id, balance FROM account_balance` debe coincidir con el `SUM()`
  legado para cada cuenta existente al momento de la migración.
- Concurrencia: dos requests simultáneos de venta para la misma cuenta con saldo justo para
  una sola venta — solo una debe tener éxito, la otra debe recibir `Insufficient balance`.
- Los 4 puntos de chequeo migrados deben seguir devolviendo el mismo error de dominio
  (`MyCurrentException('COM001', 'Insufficient balance')`) para no romper el contrato de la
  API.
