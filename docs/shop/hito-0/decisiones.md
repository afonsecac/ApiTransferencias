# Decisiones del hito 0

Registro de las decisiones tomadas el 2026-10-12 al cerrar el hito 0 del mercado (COMR-86). Cada una indica la historia de Jira, lo decidido, el motivo y lo que condiciona. Las del bloque B salen de la sesión del primer grupo (COMR-100); la del dinero, del bloque E (COMR-101).

> Las recomendaciones que se aceptaron salen de `docs/shop/hito-0-preparacion.md` §3 (documento de planificación). Donde el plan no fijaba recomendación, se indica que fue una **propuesta nueva** que se aceptó.

## Bloque B · Primer grupo

| Jira | Tema | Decisión | Por qué | Condiciona |
|---|---|---|---|---|
| COMR-158 (B0) | Unidades organizativas y centros de costo | **Entidad `OrgUnit` nueva** (nombre, tipo: plataforma, vendedor, socio de almacén, transportista, comprador; centro de costo; datos fiscales). De ella cuelgan `MarketSeller`, el socio de almacén, `MarketCarrier` y, para los compradores, el `Client` actual (se le añade la referencia). Todo movimiento con costo o ingreso lleva su centro de costo | Decidirlo antes de crear vendedores y almacenes evita migrar imputaciones después | Hito 1 (identidad y catálogo), hito 6 (facturación) |
| COMR-170 (B1) | Identidad y acceso de usuarios externos | **Un único mecanismo**: usuario del dashboard con rol propio por tipo (`ROLE_MARKET_SELLER`, `ROLE_MARKET_CARRIER`, `ROLE_MARKET_WAREHOUSE`) vinculado a su `OrgUnit`, con prefijos de rutas propios (`/seller/api/*`, `/carrier/api/*`, `/warehouse/api/*`) y su regla de acceso, reutilizando JWT y 2FA. Aislamiento aplicado en los servicios con un contexto de usuario externo, no con Voters. Pantallas en `dashboard-cm` con layout propio por tipo | Hoy `^/dashboard/api` exige `ROLE_SYSTEM_USER` y no hay Voters: un vendedor con ese rol lo vería todo. Un prefijo propio no toca el firewall actual; una app aparte duplicaría autenticación y 2FA | **Es la única que condiciona el diseño del hito 1** (`seller_id` y el contexto de vendedor en los servicios) |
| COMR-171 (B2) | Vendedor y marca | **Entidades separadas**; la marca pertenece a un vendedor | Un vendedor con varias marcas no obliga a duplicarlo | Hito 1 |
| COMR-172 (B3) | Vendedor de plataforma | **Un único registro** con `isPlatform = true`, protegido | Todo producto propio lo usa por defecto sin casos especiales | Hito 1 |
| COMR-173 (B4) | Identidad del producto | **`sku` único global e inmutable**; el consumidor usa `id` como identificador y recibe `sku` | Evita colisiones entre vendedores y simplifica el sincronizador | Hito 1 y contrato con ApiWebComremit (hito 4) |
| COMR-174 (B5) | Categorías | **Árbol de hasta 3 niveles** con `parent_id`, nombres es/en, sin ruta materializada | Suficiente para navegar; se materializa solo si se mide un problema | Hito 1 |
| COMR-175 (B6) | Listas cerradas | Áreas iniciales **Gift Cards, Retail y Supermarket**; el negocio aporta el resto cuando las tenga | Son enumeraciones validadas en los DTO; cambiarlas luego es una migración pequeña | Hito 1. El selector de área del routing hoy ofrece Gift Cards y Retail y sigue deshabilitado: hay que añadir Supermarket al crear los productos con área |
| COMR-176 (B7) | Tarjeta de compra | **Producto digital que se canjea**, no producto físico con stock y entrega | Evita pasarla por almacenes y cobertura. *El plan no fijaba recomendación: fue una propuesta nueva que se aceptó; revisar si el negocio la quiere como producto físico* | Decide que no entra en el hito 2 (almacenes y cobertura) y necesita un flujo de emisión y canje propio |
| COMR-177 (B8) | Base de la tarifa de entrega | **Cada producto declara** si su entrega se tarifa por peso o por volumen y en qué unidad (g, kg, oz, lb, L…); la cobertura del almacén define tarifa por unidad, mínimo y máximo | Peso o volumen son obligatorios donde haga falta | Hito 1 (modelo de producto) y hito 2 (cobertura) |

## Bloque E · Dinero (COMR-101)

**Decisión: decimal de dos decimales, nunca `float`.**

- En las tablas de mercado (precios, tarifas, rangos de cobertura, líneas de venta) los importes se guardan como **decimal con dos decimales**. En PHP, cadenas o un objeto de valor; nunca `float`.
- **La moneda viaja siempre junto al importe.**
- La frontera con el ledger actual, que es `float`, se cruza en **un único servicio** que redondea de forma documentada; ningún otro sitio convierte.
- Las comparaciones de rangos de cobertura y de tramos de tarifa se hacen en decimal.
- En el hito 0 solo se decide y se deja escrito; el objeto de valor se implementa en el hito 1 con sus pruebas.

## Otras decisiones

| Jira | Tema | Decisión | Por qué |
|---|---|---|---|
| COMR-146 | Orden de hitos | **Se mantiene**: la entrega (hito 5) sigue detrás de oferta y venta (hito 4). *Sin recomendación en el plan: se aceptó la opción más conservadora* | Menos alcance en el hito 4. Se puede revisar si el piloto necesita aviso de salida y seguimiento |
| COMR-154 | Reconciliar con el plan de geolocalización | **Un solo nombre y rol**: `ROLE_MARKET_CARRIER` con `/carrier/api/*` (coherente con COMR-170). **Dos conceptos distintos**: la cobertura por municipio (precio y entregable) y la zona de entrega del módulo Geo (hora estimada y aprendizaje) | Evita dos roles para lo mismo y mezclar precio con estimación | Hay que **ajustar `plan-geolocalizacion-rutas.md`** y anclarlo a este plan; además, calendario (G1 antes del hito 5) y ampliación del VPS en el presupuesto, sin decidir |
| COMR-159 (T21) | Socios de almacén | El usuario del socio **puede** recibir mercancía, ajustar su stock, armar combos, aceptar traslados, preparar y entregar pedidos y ver su cobertura; **no** ve precios de venta, costos de otros socios ni datos de clientes. La **plataforma fija** la cobertura y el precio de entrega por municipio y el socio puede proponer cambios. Se le **liquida por entrega y por traslado**, con apuntes imputados a su centro de costo (`OrgUnit`) y cierres periódicos. *El plan no fijaba recomendación: son propuestas nuevas que se aceptaron* | Mínimo privilegio y precio coherente de cara al cliente |
| COMR-184 (C4) | Cabecera `Referer` en `ApiTokenAuthenticator` | **Se corrige dentro del hito 0**, en su propia PR (fusionada, #217) | Con solo conocer un token se saltaba la lista de IP de la cuenta; el hito 0 es la ventana para tocar autenticación |

## Decisiones técnicas ya registradas (otros documentos)

- Arquitectura hexagonal y eventos: `docs/arquitectura/adr-001-monolito-modular-hexagonal.md` (aceptado, COMR-152).
- Motor de rutas (D30): `a2-motor-de-rutas.md`. Fuente de las coordenadas (D31): `a1-geocodificacion.md`.

## Pendiente de registrar fuera de este repositorio

Estas decisiones también deben anotarse en `docs/plan-shop-por-fases.md` y reflejarse en los mockups antes de compartirlos. Ese documento y `docs/shop/por-definir.md` están **sin versionar** en el árbol de trabajo principal, así que no se han tocado desde aquí.
