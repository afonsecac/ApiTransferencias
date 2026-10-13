# A3 · Informes y listados que agregan ventas

Historia COMR-98 · Hito 0, bloque A · revisado sobre `develop` el 2026-10-12.

## Pregunta

¿Qué partes del sistema sumarían ventas de mercado como si fueran recargas?

## Cómo se hizo

Inventario de todo lo que lee `CommunicationSaleInfo` (y sus subtipos recarga y paquete) o la tabla `communication_sale_history` sin distinguir el subtipo: repositorios, servicios, controladores del dashboard, notificaciones, correos, comandos, tareas programadas, mensajes y la API pública. Lo hizo un agente de solo lectura y se **comprobaron a mano** en el código las filas de mayor riesgo (1, 17–18, 21, 27 y 28: las etiquetas `recharge`/`sale` por `instanceof`, `getReservedAmount`, `CheckFailedRechargesTask` y el texto «Comprobante de recarga»). Las demás filas están leídas por el agente y la fila 39 **no se pudo confirmar** (marcada); revisar al trabajar cada una.

## Cómo está guardado

`CommunicationSaleInfo` usa herencia de tabla única con discriminador `type` (`recharge`, `sale`). Un repositorio de subtipo filtra por su subtipo; el de la clase base trae todos.

Ya hay una defensa parcial desde COMR-62: `SaleSubtype::saleTypeOf()` lanza ante un subtipo desconocido y las consultas de despacho y consulta de estado se limitan a `DISPATCHABLE_SUBTYPES`. **No cubre** las agregaciones de abajo.

Leyenda de decisión: **INCLUIR** (el mercado debe entrar), **EXCLUIR** (no debe), **SEPARAR** (mostrarlo aparte) o **REVISAR** (decidir al construirlo).

## 1. Saldo y reservas

| # | Dónde | Qué hace | Si hubiera una venta de mercado | Decisión |
|---|---|---|---|---|
| 1 | `CommunicationSaleInfoRepository::getReservedAmount` | Suma `totalPrice` de ventas PENDING o RESERVED de una cuenta | Se sumaría a lo reservado | **INCLUIR**: el dinero está comprometido sea cual sea el subtipo. Depende de la fila 28 |
| 2 | `BalanceService::hasAvailableBalance` | `saldo − reservado` frente al importe | Una venta de mercado pendiente reduce el saldo disponible de recargas | **INCLUIR**: un solo monedero (cambiarlo sería política de negocio) |
| 3 | `CommunicationSaleService` (admisión de recarga, reserva y paquete) | Comprueba saldo al admitir | El flujo de mercado necesitará su propia admisión | **REVISAR**: si olvida llamar a `hasAvailableBalance`, el mercado no comprobaría saldo |
| 4 | `BalanceService::createSaleBalance` | Crea el débito al completarse una venta | Genera el débito igual que una recarga | **INCLUIR** |
| 5 | `BalanceService::reconcileMissingBalances` (`ReconcileBalancesCommand`) | Lista TODAS las ventas COMPLETED sin balance y les crea el débito | Reconciliaría ventas de mercado | **INCLUIR** |
| 6 | `BalanceService::exportToExcel` | Exporta operaciones de saldo; el teléfono solo sale si la venta es recarga | Filas de mercado con teléfono vacío y referencias de la venta | **SEPARAR**: añadir una columna con el tipo |
| 7 | `BalanceOperationRepository` (listados y exportación de saldo) | Une cada débito con su venta | Los débitos de mercado aparecen en listados y exportaciones | **INCLUIR**: son movimientos reales |
| 8 | `BalanceOperation::communicationSale` y su DTO de salida | Expone la venta dentro de `/balance/operations` | Se serializaría una venta de mercado en un DTO pensado para recargas o paquetes | **REVISAR**: confirmar que el DTO tolera campos de mercado |

## 2. Informes y estadísticas del dashboard

Todos son consultas de `CommunicationSaleInfoRepository` que llama `DashboardStatisticsService` desde `DashboardInformationController`. Filtran por un parámetro opcional `type` que solo conoce `recharge` y `sale`; con `type` vacío no filtran nada.

| # | Consulta | Qué agrega | Si hubiera una venta de mercado | Decisión |
|---|---|---|---|---|
| 9 | `getStatsSummary` | Conteos por estado, suma y media del importe, tasa de éxito | Con `type` vacío se mezcla; con `recharge` o `sale` no entra y no existe `market` | **SEPARAR**: añadir un tercer valor de `type` |
| 10 | `getStatsOperationsByClient` | Ranking por cliente | Sube el volumen e importe del cliente | **SEPARAR** |
| 11 | `getStatsOperationsOverTime` | Serie temporal | Se mezcla en la serie | **SEPARAR** |
| 12 | `getStatsBusiestDays` | Operaciones por día de la semana | Distorsiona los días punta de recargas | **SEPARAR**: es una métrica operativa de recargas |
| 13 | `getStatsPeakHours` | Operaciones por hora | Distorsiona las horas punta | **SEPARAR** |
| 14 | `getStatsTopPackages` | Top de paquetes por conteo e importe | Un producto de mercado sin paquete caería en un cubo sin identificar | **REVISAR**: el mercado no es un «paquete» |
| 15 | `DashboardStatisticsService` | Pasa `type` sin validarlo | Un `type=market` futuro llegaría al repositorio sin ninguna rama que lo trate | **REVISAR**: validar y ampliar la enumeración de `type` |
| 16 | `DashboardInformationController` (seis acciones) | Expone las cifras anteriores | Igual que las filas 9–14 | **SEPARAR**: selector de tipo en el frontend |

## 3. Listados de ventas

| # | Dónde | Qué hace | Si hubiera una venta de mercado | Decisión |
|---|---|---|---|---|
| 17 | `DashboardSalesController::list` | Lista todas las ventas; filtro `type` opcional; la etiqueta se calcula con `instanceof Recharge ? 'recharge' : 'sale'` | Aparecería en el listado sin filtro **etiquetada «sale» (paquete)**: dato erróneo en silencio | **SEPARAR (corregir)**: sustituir el ternario por un mapa de tipos y añadir `market` al filtro |
| 18 | `DashboardSalesController::detail` | Detalle con el mismo ternario | Etiqueta errónea | **REVISAR**: mismo arreglo |
| 19 | `DashboardSalesController::checkStatus` | Consulta el estado de una venta cualquiera | `checkStatusOrder` lanzaría `UNSUPPORTED_SALE_SUBTYPE` | **REVISAR**: el mercado puede no tener consulta de proveedor |
| 20 | `DashboardSalesController::retry` | Reintento manual por subtipo | Lanza error 500 ante un subtipo desconocido | **REVISAR**: decidir si el mercado es reintentable |
| 21 | API pública `GET /communication/sale` y `/{id}` | Lista las ventas de la cuenta, sin filtro de tipo | El cliente de la API vería ventas de mercado mezcladas con recargas | **SEPARAR**: recurso aparte o campo `type` visible |
| 43 | (misma colección) | — | — | Ver fila 21 |

## 4. Notificaciones y correos

| # | Dónde | Qué hace | Si hubiera una venta de mercado | Decisión |
|---|---|---|---|---|
| 22 | `NotificationCenterService::countRecentRecharges` y SSE (filas 22–23) | Contador «live» de recargas, con el repositorio del subtipo | Nada: el mercado no cuenta como recarga | **EXCLUIR** (hará falta un contador paralelo si se quiere verlo) |
| 24 | `TrySaleAgainCommand` | Reintento manual; busca por el repositorio de recargas | Con un id de mercado no encuentra la venta ni notifica | **REVISAR** |
| 25 | `CommunicationsDispatchService::dispatchPending` y correo resumen | Cuenta recargas y paquetes encolados; texto «Recargas encoladas» | Ya excluido de la selección; no aparece | **EXCLUIR** hasta que exista su flujo; luego **SEPARAR** con una línea «mercado» |
| 26 | `RechargeVerificationService` y `RechargeVerificationController` | Endpoint público (QR) que verifica una venta COMPLETED | Una venta de mercado se verificaría como «recarga» | **REVISAR**: ¿el QR de recarga debe servir para mercado? |
| 27 | `RechargeReceiptPdfService` y `receipt/recharge.html.twig` | PDF «Comprobante de recarga» | El recibo diría «recarga» («validar esta recarga») | **SEPARAR**: plantilla propia para mercado |

## 5. Tareas programadas, comandos y colas

| # | Dónde | Qué hace | Si hubiera una venta de mercado | Decisión |
|---|---|---|---|---|
| 28 | `CheckFailedRechargesTask` (cada 2 min) | Pone en PENDING **toda** venta FAILED, de cualquier subtipo | Una venta de mercado fallida volvería a PENDING cada 2 minutos; como `getLastPending` la excluye, nadie la reprocesaría y quedaría PENDING para siempre, **inflando lo reservado** (fila 1) | **EXCLUIR (riesgo alto)**: restringir a `DISPATCHABLE_SUBTYPES`. Ya está fijado por pruebas de caracterización (COMR-255) |
| 29 | `getLastPending` y `CheckStatusTask` (cada hora) | Sondea ventas PENDING | No entra | **EXCLUIR**: ya protegido |
| 30 | `findAwaitingProvider`, `countAwaitingProvider`, `dispatchAwaitingProviderSales` | Ventas sin proveedor | No entra | **EXCLUIR**: ya protegido |
| 31 | `findPendingUndispatched*`, `countPendingUndispatched` | Pendientes sin despachar | No entra | **EXCLUIR**: ya protegido |
| 32 | `ActivateReservedSalesCommand` y `SaleRechargeTask` | Activa reservas de promoción | No entra (repositorio del subtipo) | **EXCLUIR** |
| 33 | `BackfillSaleAccessTokenCommand` | Rellena `access_token` en ventas sin él | Incluiría el mercado | **INCLUIR**: es mantenimiento de datos |
| 34 | `CheckSaleMessageHandler`, `DTOneWebhookService`, `RechargeVerificationService` (búsqueda por `transactionId`) | Re-check por webhook de DTOne | Un `external_id` de mercado dispararía `checkStatusOrder`, que lanza; la cola reintentaría | **REVISAR**: ignorar el mercado en el webhook |
| 35 | `SalePackageMessageHandler` | Procesa mensajes de paquete | No entra | **EXCLUIR** |
| 36 | `HistoricalSaleService` | Escribe el histórico de cualquier venta | Se registra igual | **INCLUIR**: auditoría sin agregados |
| 37 | `checkStatusOrder`, rama 404 | Reintenta solo si es recarga; el resto se marca REJECTED | El mercado caería en la rama de «rechazada» con un motivo genérico | **REVISAR**: puede ser una política distinta |
| 38 | `CommunicationInfoService::querySale` | Consulta de administración por tipo de operación | No hay tipo de operación para el mercado | **REVISAR**: añadir el tipo |
| 39 | `ProviderRoutingAdminService::preview` | Cuenta las ventas PENDING del cliente para mostrar el impacto de un cambio de proveedor | Contaría ventas de mercado como afectadas por un cambio de proveedor de recargas | **EXCLUIR** (*no se pudo confirmar esta fila en el código*) |
| 40 | `SaleProviderFailoverService::promoteToFallback` | Failover de proveedor | Lanza `UNSUPPORTED_SALE_SUBTYPE` | **REVISAR**: mismo caso que la fila 37 |

## 6. API pública `/api`

| # | Dónde | Qué hace | Si hubiera una venta de mercado | Decisión |
|---|---|---|---|---|
| 41 | `CommunicationSaleProvider` (`GET /communication/sale/{id}`) | Devuelve la venta y, si lleva 15 min PENDING, la refresca | `checkStatusOrder` fallaría y el error se registraría; se devolvería la venta sin refrescar | **REVISAR**: decidir si el mercado se sondea |
| 42 | `CreateSaleInfoProcessor` (los tres `POST`) | La unicidad de `clientTransactionId` abarca toda la tabla; luego ramifica por subtipo | La unicidad cubre el mercado (bien); un tipo sin rama lanza | **INCLUIR** la unicidad; falta añadir la rama del mercado |

## Los cinco consumidores de mayor riesgo

1. **`CheckFailedRechargesTask` (fila 28).** Resucita a PENDING toda venta FAILED. Con el mercado quedaría atascada, inflaría lo reservado y bloquearía saldo de recargas. **Debe arreglarse antes de que exista ninguna venta de mercado.**
2. **`getReservedAmount` y `hasAvailableBalance` (filas 1–2).** Suman todos los subtipos; con el fallo anterior la reserva sería permanente. Hay que decidir explícitamente si el mercado reserva saldo (recomendación: sí).
3. **Estadísticas del dashboard (filas 9–16).** Seis consultas mezclan el mercado en conteos, importes y medias con `type` vacío; `type` solo admite dos valores.
4. **Etiquetas `recharge`/`sale` por `instanceof` (filas 17–18) y `GET /communication/sale` (fila 21).** Etiquetarían el mercado como «paquete» en silencio, y la API pública lo mezclaría con las recargas.
5. **Exportación de saldo (fila 6) y verificación pública con recibo PDF (filas 26–27).** La exportación contable mezcla filas de mercado sin identificarlas y el recibo diría «Comprobante de recarga».

## Resultado: lista de trabajo del hito 4 (venta)

- Corregir primero la fila 28 y fijar con tests que el mercado no vuelve a PENDING.
- Decidir y probar que el mercado entra en `getReservedAmount` (filas 1–2).
- Añadir `market` a la enumeración de `type` y a los seis informes (filas 9–16), y sustituir los ternarios por un mapa de tipos (filas 17–18).
- Separar la API pública, la exportación de saldo y el recibo (filas 6, 21, 26, 27).
- Cada fila marcada **REVISAR** se decide al construir el flujo correspondiente y se anota aquí.
