# Evaluación: ¿hexagonal, orientado a servicios y a eventos para el marketplace y la capa geo?

Estado: **decidido el 2026-10-12 (COMR-152) y en adopción**: ver §7, que recoge lo que ya está implementado a 2026-10-16. Las secciones 1 a 6 son la evaluación original (con las cifras de entonces) y se conservan como registro de la decisión; la decisión formal está en `adr-001-monolito-modular-hexagonal.md`. Afecta a `plan-shop-por-fases.md` y `plan-geolocalizacion-rutas.md`. Marcas: **VERIFICADO** (archivo:línea), **PROPUESTO**, **NO COMPROBADO**.

## 1. Diagnóstico del estilo actual

| Aspecto | Estado (VERIFICADO) | Valoración |
|---|---|---|
| Entrada API externa | API Platform con 21 State Providers/Processors en `src/State/` y recursos sin entidad en `src/ApiResource/` (`Calculator.php`, `RotateToken.php`) | Ya es un adaptador de entrada: el *processor* delega en servicios. |
| Entrada dashboard | Controladores bajo `/dashboard/api` que serializan a mano (`DashboardUserController.php:309`, `DashboardCommunicationContractsController.php:283`, `DashboardClientSecurityController.php:170-194`) | Adaptador de entrada mezclado con presentación; cada controlador repite el mapeo. |
| Servicios | 18 de 41 servicios extienden `CommonService`, que inyecta 9 dependencias (`src/Service/CommonService.php:27-36`); `CommunicationSaleService` suma 7 más y tiene 1566 líneas (`:85-101`) | Acoplamiento a `EntityManager`, `Security`, `Mailer` y `Serializer` desde la lógica de negocio; servicios-dios. |
| Entidades | `CommunicationSaleInfo` con STI (`:25-27`), 61 métodos públicos y lógica (`ensureAccessToken()` `:502`, `wasSentToProvider()` `:617`); `CommunicationContract::close()` `:371` | Hay dominio dentro de las entidades, pero mezclado con mapeo ORM y getters/setters. |
| Proveedores | Contratos en `src/Provider/Contract/`, `ProviderRegistry`, `ProviderResolver`, etiquetado por `_instanceof` (`config/services.yaml:51-55`), adaptadores `Etecsa/`, `DTOne/`, `Csq/` | **Puertos y adaptadores de facto.** Es el mejor ejemplo del repo y el que copia el plan geo. |
| Mensajería | 13 mensajes en `src/Message/` que transportan ids (`SalePackageMessage.php`) o datos de comando (`PromotionCreatedMessage.php`); RabbitMQ con colas por intención (`config/packages/messenger.yaml:16-50`); sin middleware de transacción ni outbox (VERIFICADO: no hay `middleware` en `messenger.yaml`); el despacho tras `commit()` es manual (`CommunicationSaleService.php:134-149, 410-426`) | Son **comandos asíncronos**, no eventos de dominio: un productor sabe quién consume. |
| Eventos | `src/EventListener/` solo tiene listeners de kernel y Doctrine (`ExceptionListener`, `SysConfigCacheListener`, …) | No existen eventos de dominio. |
| Tareas | `src/Schedule/Task/*` con `#[AsCronTask]` (`PingProvidersTask.php:27`) | Correcto; es un adaptador de entrada temporal. |
| Errores | `MyCurrentException` con código de dominio y HTTP juntos | Mezcla dominio y transporte, pero es uniforme. |
| Autorización | Sin Voters (VERIFICADO: `src/Security/` no tiene ninguno); `access_control` y filtro `CurrentUserExtension` (`src/Doctrine/`) | Reglas de acceso dispersas; con socios de almacén (T21) y unidades organizativas (T22) hará falta algo mejor. |
| Tests | 159 archivos; 122 extienden `TestCase` (dobles); `FunctionalTestCase` sobre `KernelTestCase` con BD real (`tests/Functional/FunctionalTestCase.php:19`) solo tiene 4 subclases; tests de controlador con mocks (`AdminPromotionControllerTest.php:17`) | Buena base unitaria; poca cobertura de infraestructura real. |
| Análisis estático | PHPStan nivel 5 (`phpstan.neon`); **no hay Deptrac, phpat ni similar** en `composer.json` | Nada impide hoy que un controlador toque el `EntityManager`. |

Acoplamientos que harán daño al crecer: `CommonService` (cualquier servicio nuevo hereda mailer y hasher sin usarlos), controladores que serializan entidades (cada campo nuevo se olvida en algún sitio: es el origen de los bugs de 2026-08-27 citados en `CLAUDE.md` §12), STI de ventas que crecerá con el subtipo de mercado (T7) y la ausencia de eventos: hoy "venta creada" se traduce en N llamadas directas desde `CommunicationSaleService` (balance, histórico, bus, proveedor).

## 2. Opciones

| | a) Seguir con disciplina | b) Monolito modular hexagonal | c) Microservicios con bus |
|---|---|---|---|
| Ventajas | Cero curva; coherente con lo existente | Fronteras explícitas por contexto; dominio probable sin BD; eventos desacoplan venta de entrega, notificación y aprendizaje; una sola BD y un despliegue | Escalado y despliegue independientes; aislamiento de fallos |
| Costes | Las deudas de §1 se replican en 11 capacidades nuevas (A–N del plan); el marketplace acabará en otro servicio de 1500 líneas | Más clases (DTO, puerto, adaptador); regla de dependencias que hay que vigilar; convivir con dos estilos | Dos o más despliegues, contratos versionados, observabilidad distribuida, consistencia eventual, duplicar auth y tenant; el equipo es pequeño |
| Riesgos | Imposible extraer nada después; tests lentos y frágiles | Sobreingeniería si se aplica a contextos triviales | Venta y saldo **comparten tabla y transacción** con comunicaciones por decisión T1/T7 (`plan-shop-por-fases.md:15,21`): separarlos rompe la atomicidad |

Para c), lo único con **razón física** para separarse es lo que ya se separa: el motor geo (otro VPS, sin estado, ver `docs/geo/definicion-vps-geolocalizacion.md`) y la app del mensajero (otro proceso por naturaleza). Notificaciones y rutas no ganan nada separándose: son consumidores de eventos dentro del monolito y ya tienen cola propia (`async_notifications_high`).

## 3. Recomendación: **b)**, con frontera de servicio solo donde hay razón física (PROPUESTO)

### 3.1 Carpetas

```
src/Market/<Contexto>/            Catalog, Inventory, Distribution, Pricing, Sales, Fulfillment, Billing, Notification, Learning
  Domain/        entidades y objetos de valor puros, eventos, excepciones, interfaces de repositorio (puertos de salida)
  Application/   casos de uso (Command + Handler), consultas (Query + Handler), DTO de entrada/salida, puertos (interfaces)
  Infrastructure/ Doctrine (mapeo y repositorios), Http (controladores dashboard, State API Platform), Messenger (consumidores), Cli
src/Delivery/Geo/{Domain,Application,Infrastructure}   igual, con el nombre que ya fija el plan geo
```

- **Entradas**: los `State` de API Platform y los controladores `/dashboard/api` viven en `Infrastructure/Http` y solo hacen: DTO `IInput` validado por el resolver (`CLAUDE.md` §10) → `CommandBus`/handler → DTO de salida. Se mantiene la convención de DTO de `src/DTO/` para lo existente.
- **Salidas**: repositorios por interfaz en `Domain`, implementación Doctrine en `Infrastructure`. Mapeo Doctrine en XML o atributos sobre la propia entidad de dominio (pragmático: no duplicar entidad y modelo; sí prohibir que el dominio use `EntityManager`). El gateway geo es un adaptador HTTP de `MapsRoutingInterface`.
- **Contextos entre sí**: solo por (1) eventos de dominio y (2) puertos síncronos explícitos (`StockReservationPort`) cuando la atomicidad lo exige. Nunca inyectar un servicio de otro contexto.
- **Transacción venta + saldo + stock**: un solo caso de uso `PlaceOrderHandler` en `Sales/Application`, una transacción Doctrine, llamada síncrona al puerto de `Inventory` (misma conexión, mismo lock pesimista que hoy usa `BalanceService`) y publicación de `OrderPlaced` **en la misma transacción** mediante un transporte Doctrine de Messenger (`doctrine://default?queue_name=domain_events`): es un outbox sin código propio. Un `worker-events` lo consume y reenvía a RabbitMQ solo si hace falta prioridad. Los comandos existentes siguen en AMQP.
- **Eventos**: `final readonly class OrderPlaced { eventId (UUID), occurredAt, tenantId, orderId, lines[] }`. Solo identificadores y datos inmutables necesarios; no entidades. Consumidores idempotentes por `eventId` (tabla `processed_events(consumer, event_id)`), at-least-once, sin garantía de orden entre agregados.
- **CQRS ligero: sí.** Las lecturas de listado del dashboard son `Query` con DBAL/QueryBuilder en `Infrastructure/Read` que devuelven DTO; sin proyecciones ni event sourcing.
- **Autorización**: Voters por contexto (`CanManageWarehouse`) en `Infrastructure/Security`, obligatorios desde que existan socios (T21).
- **Pruebas**: `Domain` con `TestCase` puro; `Application` con puertos en memoria; `Infrastructure` con `FunctionalTestCase` y Postgres real (hoy solo 4; es donde más hay que crecer); un test de contrato por evento (esquema serializado estable).

## 4. Eventos de dominio entre contextos (PROPUESTO)

| Evento | Productor | Consumidores | Garantías |
|---|---|---|---|
| `OrderPlaced` (reserva de stock ya hecha en la misma transacción) | Sales | Fulfillment (crea orden de entrega borrador), Notification (confirmación), Learning (registro) | outbox; idempotente por `eventId` |
| `StockReservationExpired` | Inventory (tarea programada) | Sales (cancela orden y libera saldo) | compensación; no síncrono |
| `DeliveryOrderCreated` | Fulfillment | Geo (geocodifica destino, asíncrono), Notification | reintentos; Geo degrada a `Zone` |
| `DeliveryDispatched` (salió con ETA) | Fulfillment | Notification (aviso con hora), Geo (plan de ruta activo) | idempotente |
| `ShipmentDelivered` / `DeliveryFailed` | Fulfillment (app del mensajero) | Sales (cierra o incidencia), Inventory (consume o libera reserva), Notification, Learning (previsto vs real), Geo (pin confirmado) | idempotente; `DeliveryFailed` dispara compensación en Sales |
| `TransferDispatched` / `TransferReceived` | Inventory (traslado T20) | Billing (abre y cierra ficha de costo), Learning | orden por agregado no garantizado: `Received` sin `Dispatched` se reintenta con retraso |
| `CostSheetIssued` | Billing | Billing (factura entre centros de costo, T22), Notification al socio | idempotente |
| `EtaLearned` (factor promovido) | Learning | Geo (`EtaService` usa el nuevo factor) | versión en el evento; reversible |

Lo síncrono por puerto (no evento): reserva de stock y de saldo en la venta; cálculo de precio por cliente al confirmar; selección de almacén.

## 5. Adopción incremental

| Hito | Acción | Resultado |
|---|---|---|
| 0 | **ADR-001** "monolito modular hexagonal con eventos; comunicaciones no migra"; plantilla de contexto (carpetas, `CommandBus`, `EventBus`, `processed_events`, `FunctionalTestCase` por contexto); transporte `domain_events` Doctrine y `worker-events` en los compose (hay que añadirlo a la lista explícita de `deploy-prod.yaml:69-72`) | Convención escrita antes de la primera línea |
| 0 | Herramienta de dependencias: **phpat** como extensión del PHPStan que ya corre en CI (`phpstan.neon`, `ci.yaml`): `Domain` no importa Symfony, Doctrine ni `App\Service`; `Application` no importa `Infrastructure`; un contexto no importa `Infrastructure` de otro. Deptrac es la alternativa si se prefiere archivo propio | Fallo de CI al cruzar capas |
| 1 | **Catálogo (A) como primer contexto completo**: dominio puro, casos de uso, repositorio Doctrine, controladores dashboard como adaptador, consultas de lectura, Voters, los tres niveles de test | Ejemplo real para el resto |
| 1 | Regla para código nuevo en comunicaciones: no extender `CommonService` (ya en `CLAUDE.md` §5), publicar `CommunicationSaleDispatched` cuando el marketplace lo necesite, sin reescribir | Convivencia sin pagar dos veces |
| Fuera | No migrar comunicaciones, promociones ni contratos a la nueva estructura; no event sourcing; no microservicios salvo geo | Foco |

## 6. Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| Sobreingeniería para un equipo pequeño | Capas solo en contextos con reglas (Sales, Inventory, Fulfillment, Geo); contextos de catálogo o datos maestros pueden tener `Application` e `Infrastructure` delgadas; sin interfaz donde solo habrá una implementación salvo puertos de salida |
| Duplicación DTO/entidad | Entidad de dominio mapeada con Doctrine (sin modelo duplicado); DTO solo en bordes; serializadores del DTO de salida con test de ida y vuelta (regla `CLAUDE.md` §12) |
| Latencia por evento donde hacía falta una llamada | Lista cerrada de puertos síncronos (§4); el resto, evento; la UI muestra estados intermedios (`reservada`, `en preparación`) |
| Eventos perdidos | Outbox Doctrine en la misma transacción; consumidor idempotente; cola `failed` ya existente (`messenger.yaml:14`); tarea de reconciliación que compara `orders` sin `delivery_order` tras N minutos |
| Dos estilos conviviendo | ADR y phpat; `CommonService` prohibido en `src/Market` y `src/Delivery`; revisión de PR por contexto |
| `FunctionalTestCase` escaso | Un test con BD real por repositorio y por consumidor de evento como criterio de "hecho" |

## Resumen

1. Adoptar **b): monolito modular hexagonal con eventos de dominio**, no microservicios; la única frontera de servicio es el VPS geo (stateless) y la app del mensajero.
2. Reutilizar lo que ya es hexagonal (`src/Provider/`: contrato, registro, resolvedor) como patrón, y dejar comunicaciones como está.
3. Venta, saldo y stock en una sola transacción con puerto síncrono; todo lo demás por eventos con outbox vía transporte Doctrine de Messenger y consumidores idempotentes.
4. Convención `src/Market/<Contexto>/{Domain,Application,Infrastructure}` y `src/Delivery/Geo`, con phpat en el PHPStan de CI y Catálogo como primer contexto ejemplo en el hito 1.
5. Crecer en tests de infraestructura con BD real y añadir Voters desde que haya socios de almacén.

**Decisiones del usuario**: (1) aceptar b) y el ADR-001 con comunicaciones fuera de la migración; (2) phpat (dentro de PHPStan) o Deptrac para la regla de capas; (3) outbox con transporte Doctrine y `worker-events` nuevo frente a seguir despachando a mano tras `commit()` como hoy.

## 7. Estado de adopción (VERIFICADO el 2026-10-16)

Las tres decisiones pendientes quedaron resueltas el 2026-10-12 (ADR-001, COMR-152): se acepta b), la regla de capas va con **phpat dentro del PHPStan** y el outbox usa el **transporte Doctrine de Messenger con `worker-events`**. Contraste de lo propuesto en §3 y §5 con el código:

| Propuesta | Estado | Dónde |
|---|---|---|
| ADR con la convención y comunicaciones fuera de la migración | Hecho | `docs/arquitectura/adr-001-monolito-modular-hexagonal.md` (aceptado el 2026-10-12) |
| Regla de capas en CI | Hecho (phpat, no Deptrac) | `phpstan.neon:3`; reglas y pruebas de que detectan violaciones en `tests/Architecture/MarketLayersTest.php` y `RulesDetectViolationsTest.php`, incluida la prohibición de extender `CommonService` en código nuevo |
| Contextos `src/Market/<Contexto>/{Domain,Application,Infrastructure}` | Hecho en seis contextos: `Catalogo`, `Organizacion`, `Geografia`, `Inventario`, `Distribucion` y `GiftCards` (los nombres son en español, no los del §3.1) | `src/Market/*`; el catálogo fue el primer contexto completo, como se proponía |
| Rutas y mapeo Doctrine de los contextos | Hecho | `config/routes/contextos_http.php`; carpetas `Domain` registradas en `config/packages/doctrine.yaml` |
| Outbox con transporte Doctrine y consumidores idempotentes | Hecho | puerto `src/Shared/Application/Event/EventBus.php`, `MessengerEventBus`, `IdempotentConsumer` y `DbalProcessedEvents` en `src/Shared/Infrastructure/Event/`; transporte `domain_events` en `config/packages/messenger.yaml:95` y enrutado de `DomainEvent` en `:115` |
| `worker-events` en los despliegues | Hecho | servicio en `docker-compose.vps.prod.yaml:151` y en la lista explícita de `.github/workflows/deploy-prod.yaml:111` |
| Un test de contrato por evento | Hecho como regla | `AssertsEventContract` (ver `CLAUDE.md`, regla 13) |
| Pruebas por capa con puertos en memoria y BD real | En marcha | cada contexto tiene `tests/Market/<Contexto>/{Application,Domain,Infrastructure}`; 11 clases de prueba extienden `FunctionalTestCase` directamente (eran 4 en §1) |
| Voters por contexto | **Pendiente** | no hay ningún Voter en `src/` (`src/Security` solo tiene autenticadores y utilidades); se hacen con el acceso del socio de almacén (COMR-288) |
| Contextos de venta, entrega, facturación, notificación y aprendizaje (`Sales`, `Fulfillment`, `Billing`, `Notification`, `Learning`) y `src/Delivery/Geo` | **Pendiente** | aún no existen; llegan con los hitos 3 a 8 del plan |
| Eventos de la §4 (`OrderPlaced`, `StockReservationExpired`, …) | **Pendiente** | los productores son los contextos de venta, entrega y traslado, aún sin construir |
| Comunicaciones, promociones y contratos sin migrar; sin event sourcing; sin microservicios salvo geo | Se mantiene | decisión del ADR |

Lo que sigue abierto de §1 y no depende de esta adopción: `CommonService` y `CommunicationSaleService` siguen como estaban (se tocan solo cuando el mercado lo obligue), y el mensajero y el motor geo siguen siendo las únicas fronteras de servicio previstas.
