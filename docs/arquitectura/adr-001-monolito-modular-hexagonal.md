# ADR-001 · Monolito modular hexagonal con eventos para lo nuevo

- **Estado:** aceptado el 2026-10-12 (decisión COMR-152): opción b), phpat dentro del PHPStan de CI y outbox con transporte Doctrine de Messenger y `worker-events`.
- **Fecha:** 2026-10-12
- **Alcance:** código nuevo del mercado (`src/Market`) y de la capa de geolocalización (`src/Delivery`). Comunicaciones, promociones y contratos **no se migran**.

## Contexto

Estado verificado del repositorio en la fecha del ADR:

- 41 servicios en `src/Service`; 18 extienden `CommonService`, que inyecta 9 dependencias (`EntityManager`, `Security`, `Mailer`, `Serializer`…). `CommunicationSaleService` tiene 1566 líneas.
- Mensajería: 13 mensajes en `src/Message` sobre RabbitMQ con colas por intención. Son comandos asíncronos, no eventos de dominio. No hay middleware de transacción ni outbox; el despacho tras el `commit()` es manual.
- No hay eventos de dominio: `src/EventListener` solo contiene listeners de kernel y de Doctrine.
- Proveedores (`src/Provider`): contrato, registro y resolvedor. Es el mejor ejemplo del repo de puertos y adaptadores.
- Seguridad: un firewall `dashboard` en `^/dashboard/api`; `^/api` exige `ROLE_API_USER`; las rutas fuera de esos dos prefijos no tienen regla de acceso. No hay Voters.
- Pruebas: 155 archivos; solo 4 subclases directas de `FunctionalTestCase` (más 30 de `ProviderFunctionalTestCase`).
- PHPStan nivel 5 sin ninguna herramienta de reglas de dependencias.

El mercado añade unas once capacidades nuevas (catálogo, inventario, distribución, precios, venta, entrega, facturación, notificación, aprendizaje…). Si se construyen con el estilo actual, replican las deudas anteriores en cada una.

## Opciones

| | a) Seguir con disciplina | b) Monolito modular hexagonal | c) Microservicios con bus |
|---|---|---|---|
| Ventaja | Sin curva de aprendizaje | Fronteras explícitas por contexto, dominio probable sin base de datos, eventos que desacoplan venta de entrega y notificación; una sola base de datos y un solo despliegue | Escalado y despliegue independientes |
| Coste | Las deudas actuales se replican en cada capacidad | Más clases; hay que vigilar la regla de dependencias; conviven dos estilos | Varios despliegues, contratos versionados, consistencia eventual, duplicar autenticación |
| Riesgo | Imposible extraer nada después | Sobreingeniería si se aplica a contextos triviales | La venta y el saldo comparten tabla y transacción con comunicaciones: separarlos rompe la atomicidad |

## Decisión

Se adopta **b)**.

1. Cada contexto vive en `src/Market/<Contexto>/` con tres capas: `Domain`, `Application` e `Infrastructure`. La capa de geolocalización vive en `src/Delivery/Geo/` con la misma estructura.
2. **Domain** contiene entidades, objetos de valor, eventos, excepciones de dominio e interfaces de repositorio. No importa Symfony, Doctrine ni `App\Service`, con una única excepción: los atributos de `Doctrine\ORM\Mapping`, que son metadatos inertes (PHP los ignora si Doctrine no está cargado) y mantienen el estilo del resto del proyecto. `EntityManager`, repositorios y `QueryBuilder` siguen prohibidos. Cada contexto registra su carpeta `Domain` en `doctrine.yaml`.
3. **Application** contiene los casos de uso (comando + manejador, consulta + manejador), DTO de entrada y salida y los puertos. No importa `Infrastructure`.
4. **Infrastructure** contiene los repositorios Doctrine, los controladores y *state providers* HTTP, los consumidores de Messenger y los comandos de consola.
5. Un contexto nunca inyecta un servicio de otro. Se comunican solo por **eventos de dominio** o por **puertos síncronos** explícitos, y solo cuando la atomicidad lo exige (reserva de stock y de saldo en la venta).
6. Los eventos se publican **en la misma transacción** mediante un transporte Doctrine de Messenger (`domain_events`), que actúa como outbox. Los consumidores son idempotentes por `eventId` y se ejecutan en un `worker-events` propio.
7. La regla de capas se comprueba en CI con **phpat** dentro del PHPStan que ya corre. Una violación hace fallar el análisis.
8. Las lecturas de listado son consultas que devuelven DTO; sin proyecciones ni *event sourcing*.
9. Frontera de servicio solo donde hay razón física: el motor de geolocalización (otro servidor, sin estado) y la aplicación del transportista.

## Qué rige en lo nuevo y qué en lo existente

| Tema | Código existente | Código nuevo (`src/Market`, `src/Delivery`) |
|---|---|---|
| Lógica de negocio | Servicios, sección 3 de `CLAUDE.md` | Casos de uso en `Application`; el controlador solo traduce HTTP |
| Dependencias compartidas | `CommonService` | Prohibido; inyección directa de lo que se necesite |
| Errores | `MyCurrentException` (código de dominio y código HTTP juntos) | Excepciones de dominio sin HTTP; se traducen a HTTP en `Infrastructure` |
| Entrada HTTP | DTO `IInput` validado por `InputValueResolver` | Igual: se mantiene |
| Pruebas | `TestCase`, mocks | `Domain` con `TestCase` puro, `Application` con puertos en memoria, `Infrastructure` con Postgres real |
| Migraciones | Descripción obligatoria e idempotentes | Igual: se mantiene |

TDD (sección 12 de `CLAUDE.md`) y pantalla de administración con pruebas (sección 11) siguen siendo obligatorios en ambos.

## Consecuencias

- Hay que ajustar la configuración compartida antes del primer contexto: autowiring (excluir las carpetas `Domain`), mapeo de Doctrine, rutas y reglas de acceso por defecto fuera de `/api` y `/dashboard/api`. Resuelto en COMR-237: las carpetas `Domain` se excluyen del autowiring, las entidades de `Domain` se mapean con atributos y cada contexto registra su carpeta en `doctrine.yaml`, las rutas de `Infrastructure/Http` se importan solas bajo `/dashboard/api` y `access_control` termina con una denegación por defecto, todo con ejemplos y tests en `tests/Examples/Pedido`.
- Hay que añadir `worker-events` a los compose y a la lista cerrada de servicios de `deploy-prod.yaml` (COMR-252) y el transporte `domain_events` (COMR-238); ambos hechos.
- Cada evento tiene un test de contrato con su esquema serializado estable (COMR-253).
- El código de comunicaciones no cambia. Si el mercado necesita reaccionar a una venta de comunicaciones, se publica un evento desde allí sin reescribir el servicio.
- Riesgo de sobreingeniería: las capas completas se aplican solo a contextos con reglas de negocio (venta, inventario, entrega, geolocalización); el catálogo puede tener `Application` e `Infrastructure` delgadas.
- Riesgo de eventos perdidos: se mitiga con el outbox en la misma transacción, consumidores idempotentes, la cola `failed` existente y una tarea de reconciliación.

## Primer contexto

El catálogo es el primer contexto completo (hito 1) y sirve de ejemplo para el resto.
