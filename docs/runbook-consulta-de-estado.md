# Runbook: consulta de estado dirigida por `next_check_at` (COMR-260)

Qué cambió en la forma de consultar a los proveedores el estado de las ventas pendientes, cómo desplegarlo, cómo comprobar que funciona y cómo deshacerlo.

## Antes y después

| | Antes | Ahora |
|---|---|---|
| Quién consulta | El barrido horario `CheckStatusTask` (todas las pendientes, una a una, dentro del `worker-scheduler`) y las cadenas de reintentos de Messenger | `CheckDueSalesTask`, cada 15 s, que solo encola; consulta cada mensaje `CheckSaleMessage` |
| Cuándo se vuelve a consultar una venta | Al reintentar Messenger (cada 2 min, 4, 8…) o en la hora siguiente | Según `next_check_at`: 5 s, 15 s, 1 min, 5 min, 15 min y 1 h (tope) tras 0, 1, 2… consultas |
| HTTP 400 al consultar | Encolaba otro mensaje a 2 s, sin tope: cadenas que se multiplicaban | No encola nada; cuenta una consulta y espera la siguiente según `next_check_at` |
| Manejador | Lanzaba `Check again`, `Failed to process…` o `Sale not yet sent` para que Messenger reintentara | No lanza: una consulta y termina |
| Carga por pasada | Todas las pendientes cargadas en memoria | Hasta 20 identificadores por proveedor, sin cargar entidades |
| Venta pendiente más de 24 h | Seguía «pendiente» para siempre | Pasa a **incierta** (sigue `PENDING`, importe reservado, aparece en «Ventas por revisar») |

Reglas que no cambian: una venta nunca se reenvía por una consulta, y una incierta conserva su saldo reservado.

## Cómo funciona

1. `CheckDueSalesTask` llama a `CommunicationSaleService::dispatchDueStatusChecks()`.
2. `CommunicationSaleInfoRepository::claimDueForCheck()` selecciona las ventas `Pending` o inciertas con `next_check_at` vencido **o nulo** (las anteriores al paso 1), como mucho 20 por proveedor, con `FOR UPDATE SKIP LOCKED`, y adelanta su `next_check_at` 90 s (el «arrendamiento»).
3. Se encola un `CheckSaleMessage` por venta.
4. `checkStatusOrder()` consulta al proveedor, y si la venta sigue pendiente cuenta la consulta y fija el `next_check_at` siguiente (`SaleCheckBackoff`).
5. Si el worker muere a mitad, el arrendamiento caduca a los 90 s y la venta vuelve a tocar.

## Orden de despliegue

1. **Paso 1** (columnas `next_check_at` y `check_attempts`, con backfill y escritura): PR #220, ya en `develop` y en staging. La migración `Version20261012170000` se ejecuta antes de levantar el código nuevo (`deploy.sh --migrate`).
2. **Paso 2** (esta PR): lee `next_check_at`. No lleva migración.

Si los dos pasos llegan a producción en la misma versión, es seguro: el backfill marca las pendientes y, aunque alguna quedara sin `next_check_at`, el valor nulo cuenta como «toca ya».

Durante la transición pueden quedar en RabbitMQ mensajes `CheckSaleMessage` antiguos y reintentos de Messenger en vuelo. El manejador nuevo los procesa sin problema (hace una consulta y termina); como mucho habrá alguna consulta de más.

## Comprobar tras desplegar

Calendario (debe aparecer `CheckDueSalesTask` cada 15 s y **no** `CheckStatusTask`):

```bash
docker compose exec worker php bin/console debug:scheduler
```

Ventas que tocan ahora y su reparto (solo lectura):

```sql
-- Pendientes e inciertas, por proveedor, con cuántas tocan ya
SELECT provider, state_process, count(*) AS total,
       count(*) FILTER (WHERE next_check_at IS NULL OR next_check_at <= now()) AS tocan_ya
FROM communication_sale_info
WHERE state = 'Pending' AND state_process IN ('Pending', 'UNCERTAIN')
GROUP BY 1, 2 ORDER BY 1, 2;

-- Pendientes sin next_check_at (deben ir a cero con el tiempo)
SELECT count(*) FROM communication_sale_info
WHERE state = 'Pending' AND state_process = 'Pending' AND next_check_at IS NULL;
```

En el log del worker aparece `Consultas de estado encoladas: N` cuando una pasada encola algo. Una venta nueva debe pasar de `Pending` a `Completed` sin generar entradas nuevas en la cola `failed` de Messenger por `CheckSaleMessage`.

## Qué vigilar

- **Cola `failed`**: ya no debe crecer por `Check again`. Los mensajes antiguos que hay (de ventas ya terminadas) se pueden diagnosticar y vaciar con COMR-262.
- **Ventas inciertas**: tras desplegar, las pendientes de más de 24 h pasan a incierta en su siguiente consulta. Es lo esperado y aparecen en «Ventas por revisar».
- **Retraso de las consultas**: el tope es de 20 por proveedor y pasada, es decir hasta 80 por minuto y proveedor. Con 38 a 49 ventas al mes no se acerca; si algún día hubiera colas largas, se sube `CheckDueSalesTask::PER_PROVIDER_LIMIT`.
- **Zona horaria**: `next_check_at` se escribe y se compara con la hora de PHP del contenedor; el backfill del paso 1 usó `NOW()` de Postgres. Si PHP y Postgres no estuvieran en la misma zona, las primeras consultas de las ventas del backfill se adelantarían o retrasarían esa diferencia, una sola vez.

## Memoria del `worker-scheduler`

La pasada del cron maneja identificadores, no entidades. Con 601 ventas vencidas en la prueba (`DueStatusChecksFunctionalTest`), una pasada reclama 20 y su consumo de memoria extra es menor de 5 MB, sin depender del número de pendientes. **Pendiente**: medirlo en producción con datos reales (`docker stats` del `worker`, o el `memory_get_peak_usage` de la pasada), porque la prueba no cubre el consumo del propio proceso del scheduler.

## Reversa

No hay migración que deshacer: las columnas del paso 1 se quedan, inocuas.

1. Desplegar la versión anterior (o `git revert` de la PR) con `deploy.sh --build`.
2. Vuelven `CheckStatusTask` (barrido horario) y el reintento de Messenger del manejador.
3. Las ventas arrendadas con `next_check_at` en el futuro no importan: el código anterior no lo lee.

## Lo que no cubre este paso

- **Prueba de concurrencia con dos conexiones reales**: la exclusión entre workers se prueba con el arrendamiento (una segunda pasada no recibe lo ya reclamado). `FOR UPDATE SKIP LOCKED` evita además que dos pasadas simultáneas se esperen, pero no hay una prueba con dos procesos, porque las pruebas funcionales corren dentro de una sola transacción.
- **El límite por proveedor es de pasada, no global**: con muchos proveedores el total por pasada crece proporcionalmente.
