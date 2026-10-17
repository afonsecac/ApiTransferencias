# Mercado en la plataforma: plan general

Estado: **ideas generales organizadas**. Todavía no hay plan detallado por fase: se hará después, hito a hito. Los borradores de detalle anteriores están en `docs/shop/borradores/` y no son vinculantes. El histórico `docs/plan-shop-catalogo-fisico.md` se conserva solo como contexto (su §13 recoge correcciones de una revisión previa).

Mockups de la administración (ilustrativos, para validar la idea): (enlace privado, en la descripción de las historias de Jira del mercado)

### Referencias (diseños y documentos privados del proyecto)

| Qué es | Enlace |
|---|---|
| **Mockups de la administración del mercado**: 31 tableros (productos, vendedores y marcas, categorías, combos, stock, entrega, geolocalización y arquitectura). **Las pantallas del dashboard deben seguirlos**: el tablero 5 (Categorías, árbol + ficha), el 4 (Vendedores y marcas, una sola pantalla) y los 1 y 2 (listado y formulario de producto) son la referencia de lo ya construido y de lo que falta. La barra lateral de los tableros define el menú «Mercado». | (enlace privado, en la descripción de las historias de Jira del mercado) |
| **Mockups del escaparate (Comremit web y SendMundo web)**: catálogo, ficha, combos, detalle de combo, checkout y seguimiento, para las dos marcas, en escritorio y móvil. Origen de los puntos 11 a 18 de `docs/shop/por-definir.md`. | (enlace privado, en la descripción de las historias de Jira del mercado) |
| **Plan de pruebas de penetración de la plataforma** (documento de planificación, no es del mercado): plan aprobado y pestaña «Resultados preliminares» con los hallazgos. Se anota aquí porque la seguridad del mercado (identidad externa de vendedores y transportistas, B1) debe revisarse contra él. | (enlace privado, en la descripción de las historias de Jira del mercado) |

## 1. La idea en una frase

Vender productos de mercado (alimentos, aseo, tarjetas de compra en supermercados) por la misma plataforma de comunicaciones: **el mismo catálogo expuesto** (`CommunicationPackage`) y **la misma entrada de ventas** (`CommunicationSaleInfo`), con productos, stock, precios, distribución y proveedores propios del mercado.

## 2. Lo ya decidido

| # | Decisión |
|---|---|
| T1 | El catálogo expuesto a los clientes y la entrada de ventas son `CommunicationPackage` con `kind=market` (aclarado el 2026-10-14: el tipo lo dan el servicio y el subservicio, sin un campo `kind` aparte); lo digital no cambia. |
| T2 | Los productos viven en entidades propias, no en `CommunicationPackage` ni en `communication_product`. Su administración es distinta a la de paquetes. |
| T3 | Los proveedores se **generalizan** a partir de los de comunicaciones: se añade el proveedor propio `OWN` y la puerta a terceros. El renombrado del enum es posterior. |
| T4 | El `area` (hoy solo `Supermarket`…) decide el proveedor: entra en la clave de categoría y en el routing. **Es una subdivisión de un subservicio** (corregido el 2026-10-14): `Gift Cards` es el servicio, `Retail` un subservicio y `Supermarket` un área que cuelga de `Retail`. Solo existe bajo `Gift Cards` + `Retail`. |
| T5 | La unidad de medida va en la forma de beneficio (`benefits[]`), derivada del producto al leer. |
| T6 | Marca y categorías son taxonomías con tablas propias, en el producto. |
| T7 | Las ventas comparten la tabla de ventas, con un subtipo propio. |
| T8 | Varias marcas venden en la plataforma y cada una ve y fija el precio de sus productos. Vendedor (quién fija precio) y proveedor (quién despacha) son ejes distintos. |
| T9 | Cada almacén distribuye en ciertos municipios. Un municipio sin almacén que lo cubra no se despacha. |
| T10 | El precio de entrega que ve el cliente por municipio sale del almacén: una tarifa **por unidad de peso (g, kg, oz, lb…) o de volumen**, acotada por un **precio mínimo de entrega** (nunca se cobra menos) y un **precio máximo**. Qué unidad aplica a cada producto se decide al formar el catálogo. |
| T11 | Las entregas se organizan en **órdenes de entrega** que agrupan muchos pedidos por georreferenciación; al entregar la orden a un transportista se genera la ruta óptima. |
| T12 | Proceso: primero ideas generales (este documento); el plan detallado de cada fase se hace después. |
| T13 | Lo que se cobra al cliente por la entrega y lo que se paga al transportista son **dos listas distintas**: la del transportista es un listado de precios por unidad de medida (peso o volumen), para liquidarle lo entregado. |
| T15 | Más adelante habrá una **aplicación para el transportista**: ve su ruta de entrega, los datos de la persona a la que va a entregar en el siguiente envío, escanea el QR del paquete para identificarlo y saber a quién corresponde, pone el estado de la entrega y sube evidencias. |
| T16 | La notificación de la distribución se hace **tanto al comprador como al receptor de la mercancía**: salió para entrega (al crear la orden), hora estimada, y seguimiento del paquete en tiempo real. Puede ir **por varias vías**; cuáles y cómo se decide más adelante. |
| T17 | El **comprador puede ser un tercero** que vende con nuestra plataforma (p. ej. SendValu o AWS Malta LTD, hoy ya cuentas de la API), no solo un cliente final. Ese tercero compra por la API con su cuenta y recibe los avisos por sus vías de integración; él decide si los reenvía a su cliente. El receptor es siempre la persona que recibe la mercancía. |
| T18 | **Los combos tienen stock propio.** Al armar un combo se rebaja el stock de cada producto individual y pasa al stock del combo (y al revés al desarmarlo). Así una venta de productos sueltos no puede dejar corto un combo ya ofrecido. |
| T19 | **Una compra de un cliente es una orden**: todas las ventas (combos) que compra en un mismo acto se referencian a la misma orden y al mismo destinatario, aunque se entreguen en días distintos. |
| T20 | **Traslados entre almacenes**: mover combos (o productos) de un almacén a otro genera la baja en el origen y la entrada en el destino, con un estado intermedio en tránsito, y lleva su **ficha de costo** (valor al costo de lo trasladado, transporte y manipulación) para facturarlo después. |
| T21 | **Los almacenes pueden ser de socios**, con su propio usuario en el sistema: un socio gestiona el stock, el armado, los traslados y las entregas de su almacén, y ve solo lo suyo. Los traslados y la facturación entre un almacén propio y uno de socio salen de T20. |
| T22 | **Cada socio o cliente es una unidad organizativa** con su **centro de costo** asociado. Vendedores, socios de almacén, transportistas y clientes compradores (cuentas de la API) cuelgan de una unidad organizativa; todo costo, ingreso, traslado, liquidación y factura se imputa a un centro de costo. La plataforma es también una unidad organizativa. |
| T23 | En una fase del proyecto se añade **asistencia con inteligencia artificial** para el **armado de combos** (qué armar, cuántos y dónde, según stock, demanda y caducidad) y la **optimización de viajes** (agrupación de pedidos y rutas). Cómo se factura queda por definir. **Los permisos de uso de la IA se administran por cliente** (cada unidad organizativa decide si la usa y para qué). |
| T24 | Del vehículo de cada transportista se guardan **carga máxima (peso), volumen y velocidad**, para que las órdenes de entrega se **armen automáticamente** y de la forma más eficiente posible (qué pedidos caben, cuántos viajes, en qué orden y con qué hora estimada). |
| T25 | El sistema tiene **aprendizaje continuo** de todo lo que se vende y entrega: registra ventas, combos, rotación, caducidades, rutas, tiempos reales frente a estimados, incidencias y qué sugerencias se aplicaron, y genera **notas de mejora** periódicas que alimentan las sugerencias de la IA y la toma de decisiones. |
| T26 | **Unidades de medida (decidido el 2026-10-13, COMR-141):** «P» es la libra (**lb**). Se admiten **todas las unidades de peso y de volumen** (g, kg, oz, lb, ml, L…), tanto en el producto como en las tarifas de entrega (T10) y en la lista del transportista (T13). También existe la **unidad de conteo** («unidades»), para lo que no se pesa ni se mide por volumen (confirmado el mismo día). **Conversiones:** solo entre unidades **del mismo tipo** (peso con peso: g↔kg↔oz↔lb; volumen con volumen: ml↔L…); no se convierte peso en volumen ni al revés, ni se convierten unidades de conteo. Además se quiere el **peso volumétrico** (largo × ancho × alto ÷ un divisor, p. ej. 5000 cm³/kg) para tarifar por el mayor entre el peso real y el volumétrico, **si es preciso**: se decide al detallar las tarifas (hito 2) y dependerá de los datos de COMR-104 (peso y dimensiones). Por decidir: el divisor (¿uno global o por transportista/almacén?),
| T27 | **Reglas de combos y de la lista de productos (decididas el 2026-10-15):** (1) **un combo solo contiene productos**, nunca otro combo; (2) **los combos los forman los almacenes** (no «Arma tu combo» libre del cliente) y **no se mezclan productos de varios proveedores en un combo** (proveedor = cada plataforma desde la que se cargan productos: la propia u otra, p. ej. Supermarket, Katapilk o DTOne); (3) **no se sustituye un producto de un combo**: si hay que cambiarlo, los combos ya vendidos se mantienen, el stock restante del combo pasa a 0, sus productos se liberan y se vuelven a cargar en un combo nuevo con stock nuevo; (4) **el combo no se parte, pero la orden del comprador sí puede tener varias entregas**; (5) **el combo tiene stock propio y no usa el stock de un producto suelto**: cuántos se pueden armar lo limita el producto con menos stock; el armado automático solo con asistencia de IA y **siempre con confirmación humana** (refina T18 y T23); (6) **el descuento del combo se configura en ambos lados, cada uno en su ventana** (el vendedor el suyo, la plataforma el suyo); si lo pone la plataforma, quienes reciben el dinero (la plataforma y los almacenes asociados) reciben el monto ya con el descuento; (7) contadores de la categoría padre incluyen sus subcategorías; (8) el listado de productos pagina de 25 en 25 por nombre y permite elegir otro tamaño; imágenes de 4 MB (configurable); (9) **«Exportar» e «Importar» productos serán necesarios más adelante**: sin formato definido todavía, no se construyen ahora. |
| T14 | Los transportistas **tienen usuario en el sistema**. Se guardan sus datos y los del vehículo que usan, y el **régimen del vehículo** (propio del transportista, arrendado a la plataforma o de la plataforma); de ese régimen depende cuánto recibe por la distribución. |

### Detalle de T26 (unidades de medida, COMR-141)

**Productos por unidades:** un producto dado en unidades debe llevar **referencias del peso de una unidad y del volumen que ocupa** (peso y dimensiones por unidad). Con ellas la entrega se tarifa multiplicando por la cantidad (peso total, volumen total y peso volumétrico), igual que un producto por peso o volumen; así deja de haber productos sin base de tarifa. **Obligatoriedad (decidido el 2026-10-13):** son obligatorias, pero basta con **una de las dos** (peso por unidad o volumen por unidad). Si solo hay peso, no hay peso volumétrico; si solo hay volumen, la tarifa usa el volumen. Por decidir: qué se hace con los productos ya existentes sin ninguna.

**Divisor del peso volumétrico (decidido el 2026-10-13):** uno **global**, con el estándar del transporte internacional de carga (IATA: 6000 cm³ por kg, es decir 167 kg/m³; los couriers exprés usan 5000, a confirmar cuál se toma). **Actualizado el 2026-10-15 (COMR-281):** el divisor es solo **global y 6000**, y vive en la configuración del sistema (`market.measure.volumetric_divisor`, editable desde «Unidades de medida»); se descarta el divisor por producto. Hoy ningún cálculo lo usa: llegará con las tarifas de entrega (T10). 

**Unidad base de almacenamiento (decidido el 2026-10-13; cambiada el 2026-10-15, COMR-281):** una por tipo: **kilogramos** para el peso y **litros** para el volumen (y unidades enteras para el conteo); antes eran gramos y mililitros. Las unidades base están en la configuración del sistema (`market.measure.weight_base` y `market.measure.volume_base`, pantalla «Unidades de medida») y **no se pueden cambiar mientras haya productos con cantidades guardadas** (el servidor responde 409): cambiarlas reinterpretaría todos sus datos. Todo se guarda y se calcula en la unidad base, **con 6 decimales** (2,5 g son 0,0025 kg); la unidad en que el vendedor lo introdujo (p. ej. 2,5 lb) se guarda aparte, solo para mostrarla y editarla. Las tarifas y las sumas operan siempre en la unidad base. **Unidades admitidas:** peso g, kg, oz, lb y tonelada (t); volumen ml, l, fl oz y galón US (gal); conteo unidad, par (2) y docena (12). La caja, el paquete y el lote no tienen factor fijo y quedan fuera. **Dimensiones:** se guardan siempre en milímetros y se pueden escribir en mm, cm, m, pulgadas o pies; el producto recuerda la unidad en que se escribieron para mostrarlas igual al editar. El catálogo del comprador no cambia: entrega el peso en g, kg y lb y el volumen en ml. |

**Relación precio/peso (decidido el 2026-10-13, COMR-165):** en el buscador de los clientes los productos se podrán **ordenar por la mejor relación precio/peso**. Para ello el producto lleva un **campo con ese dato, calculado automáticamente** al insertar (y al cambiar el precio o el peso), no introducido a mano. Se calcula sobre la unidad base (precio por gramo) y se muestra en la unidad que corresponda (p. ej. €/lb y €/kg). **Los combos también tienen la relación precio/peso** (peso del combo = suma del peso de sus componentes por cantidad; precio = el del combo, con o sin descuento, ver pregunta 9). Propuesto para lo demás (aceptado el mismo día): un campo único por producto o combo, calculado sobre el precio base del vendedor, con los productos sin peso al final del orden; el orden por precio de contrato se puede añadir después. Por decidir: cómo se recalcula el combo cuando cambia un componente (COMR-165, COMR-70, COMR-104 y composición del hito 4).

**Productos relevantes (COMR-269, 2026-10-13):** se podrán marcar productos (y combos) como relevantes para que aparezcan **en lo alto de los resultados de búsqueda**. Decidido:
- **Nivel de relevancia**, no una marca de sí o no: a mayor nivel, más arriba.
- **Vigencia:** la marca puede tener fecha de inicio y de fin.
- **Orden del cliente:** los relevantes van arriba solo en el orden por defecto; si el cliente elige otro orden (p. ej. precio/peso), se respeta sin alterarlo.
- **Marcaje dual con alcance acotado (aclarado el 2026-10-14):** hay dos marcajes, el de la plataforma y el del **cliente o comprador** (la cuenta que consume la API, como SendValu o AWS Malta). Solo el de la plataforma vive en nuestro sistema; lo que marque el cliente o comprador lo gestiona él en el suyo y no nos interesa. No es el «vendedor» de T8, que es quien fija el precio.

Por refinar: si el vendedor de mercado (T8) puede o no marcar sobre sus productos en nuestro sistema; si aplica a combos (propuesto: sí); cuántos niveles hay y qué significan; desempate dentro de un mismo nivel; tope de relevantes a la vez; si es global o por cliente o contrato; dónde se marca en el dashboard y cómo se ve en la lectura del catálogo.

**Imágenes de productos y combos (decidido el 2026-10-14, COMR-148):** **tanto el producto como el combo tienen imágenes** (galería ordenada con una de referencia). Los combos pueden reutilizar las del producto, y además durante la **creación del combo** habrá una opción para **cargar ciertas imágenes de los productos** sin salir de esa pantalla. **Almacenamiento y límites (decidido el 2026-10-14):**
- **Dónde:** un **volumen detrás del proxy** (nginx). Las URLs de la API son estables, para poder migrar más adelante a almacenamiento de objetos sin romper a los clientes.
- **Tamaños:** se generan **tres** (miniatura, listado y detalle). Las medidas exactas se fijan al implementar.
- **Límite por imagen:** **4 MB**, **configurable** (parámetro de administración, no constante en el código).
- **Caché:** **no hay CDN**; las sirve **nginx con su caché** (cabeceras de caducidad largas, nombre de archivo con huella para invalidar al cambiar la imagen).

**Imágenes subidas desde el combo (decidido el 2026-10-14):** las que se cargan al crear el combo son **solo para ese combo**; no pasan a la galería del producto. Para reutilizar una imagen en otros combos hay que subirla en el producto.

**Máximo de imágenes (decidido el 2026-10-14):** un máximo por producto y por combo, **configurable** (parámetro de administración, como el límite de 4 MB), con **8 por defecto** para ambos.

**Formatos admitidos (decidido el 2026-10-14):** JPEG, PNG y WebP.

Por confirmar: que el nginx de producción es el que sirve el volumen (hoy el tráfico pasa por Traefik y nginx). **Medidas de los tres tamaños (decidido el 2026-10-14):** las **medidas reconocidas** (habituales en comercio electrónico). Valores propuestos, a confirmar al implementar, por el lado largo y conservando la proporción: miniatura 150 px, listado 400 px, detalle 1200 px. Se guardan como parámetros, no fijos en el código.

Por decidir: si se reconvierten a WebP al subirlas (COMR-148, COMR-169 y hito 4).

**Precio de reventa del comprador (a futuro; COMR-103):** los **compradores** (cuentas de la API, como SendValu o AWS Malta) podrán fijar el precio al que venden los productos: con un **ajuste particular por producto** o con un **porcentaje general** sobre la venta. Decidido el 2026-10-14: lo ponen **en nuestra plataforma, pero para su propio uso**; es un dato privado del comprador, que no cambia el costo, el precio del vendedor ni la liquidación. No entra en el hito 1, pero condiciona el diseño: el comprador queda vinculado a su `OrgUnit` (tipo comprador) y el precio tiene una capa más después del precio por cliente. Por decidir (hito 3 en adelante): si la API le devuelve ya el precio ajustado o lo consulta aparte; si el porcentaje general admite excepciones por producto o categoría; si la administración de la plataforma puede verlo; y si hay un mínimo (nunca por debajo de su precio de compra).

**Unidad organizativa y centro de costo, ajustes del hito 1 (decidido el 2026-10-14, COMR-103):**
- **Varios tipos por unidad:** una misma `OrgUnit` puede ser a la vez vendedor, socio de almacén, transportista y/o comprador. Ajusta COMR-158: el tipo deja de ser un único valor y pasa a ser un **conjunto de tipos** (o roles) por unidad.
- **Centro de costo = entidad con gestión propia** (no un código libre): tiene su propio alta, edición y listado, y la `OrgUnit` se asocia a uno.
- **Compradores actuales vinculados ya en el hito 1:** cada `Client` queda referenciado a una `OrgUnit` con el tipo comprador (migración de datos idempotente).
- **Jerarquía (grupo → unidades) e imputación a cada centro:** se refinan más adelante; no entran en el hito 1.

**Datos fiscales (decidido el 2026-10-14):** los que normalmente se piden para **generar facturas según el país en que se registra** la unidad. La `OrgUnit` lleva su país y los datos fiscales dependen de él. Propuesto: un núcleo común (razón social, identificador fiscal, dirección, país, correo de facturación) más campos propios de cada país, definidos como configuración por país (patrón de `getConfigSchema()` de los proveedores) y validados al guardar. **Países de inicio (decidido el 2026-10-14):** **Cuba, España, China, Panamá y Estados Unidos**; el resto de países usan solo el núcleo común. Campos propios propuestos por país (**a validar con quien lleve la contabilidad antes de implementar**, no están confirmados):

| País | Campos propios propuestos |
|---|---|
| Cuba | NIT; código REEUP si es una entidad estatal; cuenta bancaria |
| España | NIF o CIF; número de IVA intracomunitario si aplica |
| China | Código de crédito social unificado (18 caracteres); teléfono; banco y número de cuenta (los pide la factura fiscal) |
| Panamá | RUC y dígito verificador |
| Estados Unidos | EIN; estado; formulario fiscal (W-9 si es de EE. UU., W-8BEN-E si es extranjera) |

**Registro fiscal por país (decidido el 2026-10-14):** cada país tiene un **registro propio en base de datos** que define el **formato y los campos fiscales que se piden** (no van en el código). Cada campo del registro lleva: clave, etiqueta, tipo, si es obligatorio, formato de validación y texto de ayuda. Se administra desde una pantalla del dashboard y se siembra con los cinco países de inicio y un registro genérico (núcleo común) para el resto. Al guardar una unidad se validan sus datos fiscales contra el registro de su país.

Consecuencia (2026-10-14): la carga de datos fiscales es **dinámica**. El formulario del dashboard se construye a partir del registro del país elegido; el servidor valida contra ese mismo registro (no con `#[Assert]` fijos, por lo que la regla 8 de `CLAUDE.md` se cumple con un validador propio sobre el registro); los campos propios de cada país se guardan como JSON en la unidad, mientras que el identificador fiscal principal y el país van en columnas propias para poder buscarlos y evitar duplicados.

**Cambios del registro (decidido el 2026-10-14):** si cambia el registro de un país, las unidades ya guardadas **se conservan y se revalidan solo al editarlas**; los campos **se desactivan, no se borran**, para no perder datos de unidades antiguas.

Por decidir: qué campos son obligatorios en cada país (la validación de formato y la obligatoriedad son propiedades de cada campo del registro) y su validación con contabilidad.

**Varios centros de costo por unidad (decidido el 2026-10-14):** una `OrgUnit` puede tener **más de un centro de costo** (relación uno a varios). Consecuencias propuestas: uno de ellos se marca como **principal** (el que se usa cuando no se indica otro, y el que se crea al migrar los compradores existentes), y todo movimiento con costo o ingreso se imputa a un centro concreto de la unidad.

**Centro principal obligatorio (decidido el 2026-10-14):** toda unidad tiene un centro principal desde el alta; no se puede dejar una unidad sin él ni borrar o desmarcar el único principal.

**Compradores migrados (decidido el 2026-10-14):** cada comprador existente recibe una `OrgUnit` de tipo comprador con **un centro de costo principal que lleva el nombre del cliente**.

**División de COMR-103 (decidida el 2026-10-14):** A registro fiscal por país → B unidades organizativas y centros de costo → C vendedores y `seller_id` en el producto (COMR-103) y D vinculación de los compradores actuales (depende de B).

**Un único endpoint de catálogo para el comprador (decidido el 2026-10-14, COMR-70):** los productos que piden los compradores vienen **en un único endpoint**, sean de comunicaciones o de la tienda. Es el catálogo que ya existe, conforme a T1: no se crea un endpoint aparte para el mercado. Hoy `CommunicationPackage` se sirve bajo **dos URI con el mismo contenido**: `/communication/packages` (la histórica, la que usan los consumidores, también `/{id}` y `/upcoming`) y `/communication/packages/catalog` (URI propia de la V2). Corregido el 2026-10-14: el endpoint de referencia para el comprador es **`/communication/packages`**; el contrato de las dos debe seguir igual (prueba de paridad `CommunicationPackageShapeParityTest`). El orden por defecto es por `id` ascendente y existe `orderBy[price]`. Consecuencias propuestas:
- **El tipo de cada elemento lo da el servicio y el subservicio** (`service.name`, `service.subservice.name` y, si aplica, `area`; hoy `ServiceCategoryKey`), no un campo `kind` aparte (aclarado el 2026-10-14). El comprador puede filtrar por servicio y subservicio; sin filtro recibe todo. Falta ampliar el vocabulario controlado (hoy `Mobile`, `uSIM`, `Devices`, `Utilities`) con los servicios del mercado y de las gift cards propias.
- Los campos propios del mercado (unidad, peso, volumen, precio/peso, imágenes, relevancia) son **opcionales** y solo aparecen en los elementos de mercado; el contrato de comunicaciones **no cambia**.
- **El orden por defecto de comunicaciones no se toca**: hay consumidores que leen la primera página en orden ascendente. La relevancia y el orden por precio/peso solo afectan a los elementos de mercado.
- Un producto suelto solo es visible en este endpoint si está publicado como `CommunicationPackage`, y **un producto suelto también es un paquete** (decidido el 2026-10-15): se publica como un paquete de un solo producto, igual que cualquier combo. Queda abierto en la pregunta 19 de `por-definir.md` solo lo del stock (si ese paquete usa el stock del producto o se arma).
- Sin interruptor «Mercado» por cuenta (decidido el 2026-10-15): el catálogo se filtra por servicio, subservicio y área.

**Producto base: internacionalización, estados y gift cards propias (decidido el 2026-10-14, COMR-273):**
- **Internacionalización:** los textos del producto (nombre, descripción…) se guardan en varios idiomas (es y en al inicio, ampliable). Si el comprador **indica un idioma**, la API devuelve **solo** los textos en ese idioma; si **no lo indica**, devuelve **todos los idiomas**. Propuesto: el idioma se indica con la cabecera `Accept-Language` o un parámetro `lang`. Afecta a COMR-275 solo para los elementos de mercado; el contrato de comunicaciones no cambia.
- **Estados:** el producto maneja estados (borrador, publicado, retirado), que necesitan los combos y la relevancia.
- **Gift cards propias:** las que emite la plataforma se generan **como un producto que se puede usar en el mercado o en comunicaciones**. Esto matiza COMR-176 (tarjeta de compra = producto digital que se canjea): sigue siendo digital, sin stock ni entrega física, pero ya no se trata aparte del catálogo, sino como un producto más con su flujo de emisión y canje propio.

**Gift cards propias, modeladas como las de DTOne (decidido el 2026-10-14, COMR-276, en el backlog):** se toma como referencia `docs/dtone-product-types.md`:
- **Clasificación:** servicio `Gift Cards` con subservicio por categoría (en DTOne: Retail, Gaming, Cash Cards, Food, Entertainment, Travel & Transport) y, si aplica, área (T4).
- **Tipos:** valor fijo (`FIXED_VALUE_PIN_PURCHASE`) y valor variable entre un mínimo y un máximo (`RANGED_VALUE_PIN_PURCHASE`, la transacción devuelve además la denominación exacta).
- **Entrega:** un **código PIN y un número de serie** que quien la recibe canjea; no es una recarga directa.
- **Precios:** coste (mayorista) y precio de referencia (retail), como en DTOne.
- Se vende por la misma vía que ya usan los productos con PIN (`sellPackage()`), con el proveedor propio `OWN` como emisor.
Por decidir: dónde se canjea el código (en nuestra plataforma o en un tercero); el formato del código; caducidad; y qué categorías (subservicios) se ofrecen al inicio.

Por decidir: qué otros atributos lleva el producto (conservación o caducidad, código de barras); qué idiomas además de es y en; cuál es el comportamiento si falta la traducción del idioma pedido (propuesto: devolver el de respaldo, es); cómo se canjea la gift card (saldo en nuestro sistema, código para un tercero) y qué servicio y subservicio llevan (el tipo lo da el servicio/subservicio, no un `kind`); y si el área de la gift card propia es `Gift Cards` (COMR-175, COMR-176, COMR-273).

## 3. Mapa de capacidades

| | Capacidad | Qué incluye | Qué se reutiliza de hoy |
|---|---|---|---|
| **A** | Catálogo de productos | vendedores, marcas, categorías, productos (con unidad, peso, dimensiones y volumen) y su **galería de imágenes** ordenada con una de referencia (tipos sugeridos: frente, detalle de etiqueta, pack, ambiente), reutilizable en los combos | convenciones de administración y pantallas |
| **B** | Proveedores y suministro | proveedor `OWN` y terceros, suministro por producto y proveedor, routing por cliente y `area` | registro de proveedores, routing, failover |
| **C** | Inventario | almacenes, stock de productos **y de combos**, armado y desarmado de combos (movimiento que rebaja componentes y suma al combo), **traslados entre almacenes** (salida, tránsito, entrada) con ficha de costo y facturación posterior, movimientos, reservas con caducidad | patrón de reserva de saldo y tareas programadas |
| **D** | Distribución | municipios, cobertura por almacén, tarifa por unidad de peso o volumen con mínimo y máximo de entrega, selección de almacén | conversión de moneda |
| **E** | Precios | costo, precio del vendedor, ajuste de plataforma, precio por cliente | resolución de precio por cliente (no los contratos) |
| **F** | Oferta al cliente | combos (paquetes de mercado) dentro de `CommunicationPackage`, cada uno con su **listado ordenado de imágenes**, una de ellas la **imagen de referencia** del combo (obligatoria para publicar; es la del listado, el catálogo del cliente y la etiqueta del bulto), con tamaños generados y URLs en la API, visibilidad por cuenta, lectura con disponibilidad por municipio | catálogo, autenticación por token y paginación |
| **G** | Venta | **orden de compra** que agrupa las ventas de un mismo acto para un mismo destinatario, subtipo de venta por combo, instantáneas, reserva de stock y saldo, idempotencia, estados por venta y por orden, entregas en días distintos | saldo, `clientTransactionId`, historial |
| **H** | Entrega | geocodificación, transportistas con usuario, datos, vehículo y régimen, lista de precios por unidad de medida según régimen, órdenes de entrega, ruta óptima, estados y prueba de entrega, reentrega y reembolso, liquidación al transportista | mensajería asíncrona, notificaciones, usuarios y roles del dashboard |
| **I** | Unidades organizativas, vendedores, socios y terceros | unidades organizativas con centro de costo (plataforma, vendedores, socios de almacén, transportistas, clientes), portal del vendedor, acceso del socio de almacén a su stock, armado, traslados y entregas, aislamiento por unidad, liquidación y facturación entre centros de costo, primer proveedor tercero | roles y panel existentes, `Client`/`Account` como unidad del comprador |
| **L** | Notificación y seguimiento | avisos al comprador (un tercero integrado por API o un cliente final) y al receptor («salió para entrega» con hora estimada al crear la orden, siguiente parada, entregado, ausente o reprogramado) por una o varias vías configurables, enlace de seguimiento sin registro, estado por parada y, con la app, posición del transportista en tiempo real | notificaciones existentes, eventos de la orden de entrega (**H**), posición desde la app (**K**) |
| **M** | Asistencia con IA | sugerencias de armado de combos (qué, cuántos, en qué almacén) y optimización de viajes (agrupación de pedidos, orden de paradas, carga del vehículo), siempre como propuesta que una persona confirma; permisos por cliente o unidad organizativa (activar, para qué funciones, límites de uso); registro de uso para facturar cuando se decida |
| **N** | Aprendizaje continuo | registro de todo lo vendido y entregado (ventas, combos, rotación, caducidades, rutas, tiempos reales vs estimados, incidencias, sugerencias aplicadas o rechazadas), tablero de **notas de mejora** por unidad y por almacén, y realimentación de las sugerencias de **M** y de los parámetros de **H** (tiempos por parada, velocidades reales) | motor de rutas (**H**), stock y composición (**C**, **F**), configuración por cliente |
| **K** | App del transportista | ruta del día, siguiente entrega con datos del destinatario, escaneo del QR del paquete, estado de la entrega y evidencias (fotos, firma), funcionamiento sin conexión y sincronización | API con token y roles, almacenamiento de archivos, órdenes de entrega (**H**) |
| **J** | Transversal | seguridad, observabilidad, despliegue gradual, contrato con ApiWebComremit, pantallas y pruebas de cada capacidad | CI, PHPStan, convenciones del repo |

### 3.10 Vía de geolocalización, rutas y autoaprendizaje (plan aparte)

Existe un plan propio, `docs/plan-geolocalizacion-rutas.md` (con `docs/analisis-geolocalizacion-rutas-cuba.md`), que se ejecuta **en paralelo** a este con sus fases G0–G6 (~18-22 semanas, otro equipo). Lo que ese plan ya fija y aquí se asume:
- Módulo `App\Delivery\Geo` con capacidades (geocodificar, rutas y matriz, optimizar, cartografía), proveedores intercambiables detrás de una **puerta de cumplimiento por territorio** (los comerciales de EE. UU. empiezan denegados para Cuba) y un adaptador `Zone` que siempre funciona (centroide de municipio o zona, ETA por etapas, orden de paradas por vecino más cercano).
- Pila abierta autoalojada: OSM, motor de rutas (candidato Valhalla), VROOM para optimizar, **geocodificación con índice propio de intersecciones** («calle X entre Y y Z») más **memoria de direcciones** (`GeoAddressPin`) alimentada por los puntos confirmados al entregar. Los geocodificadores estándar fallan con direcciones cubanas.
- **App del mensajero nativa** (Expo), sin conexión primero, con `ROLE_COURIER`, token por dispositivo y `/api/courier/*`; ingesta de posiciones por lotes desde **G1**, antes de lo que este plan suponía (hito 7).
- **El comprador nunca ve coordenadas**: solo etiqueta de barrio o reparto, avance y ETA (p50/p80).
- Autoaprendizaje de ETA por zona y tramo horario, en modo sombra con barreras y reversión; sin perfilar mensajeros.
- Infraestructura: contenedores internos para motor, VROOM, geocodificador y teselas; **hay que ampliar el VPS**.

Lo que hay que reconciliar entre ambos planes (pregunta 27 de `por-definir.md`): nombres (aquí «transportista» / `ROLE_MARKET_CARRIER`, allí «mensajero» / `ROLE_COURIER`), la cobertura por municipio (T9) frente a las «zonas de entrega» del módulo Geo, el calendario (G1 debe estar antes del hito 5), y que ese plan se ancla al borrador histórico `plan-shop-catalogo-fisico.md` (§5.1, `ShopShipment`) en lugar de a este documento.

### 3.11 Dos definiciones de apoyo (revisión independiente del 2026-10-12)

- `docs/geo/definicion-vps-geolocalizacion.md`: la geolocalización **se separa a otro VPS** sin estado de negocio (Valhalla, VROOM, PostGIS con índice de intersecciones, teselas, gateway propio con contrato `/v1`), unido por WireGuard, con salida a Internet denegada por defecto; `Geo*`, `EtaService`, puerta de cumplimiento, aprendizaje e ingesta de posiciones del mensajero se quedan en ApiTransferencias.
- `docs/arquitectura/evaluacion-hexagonal-servicios-eventos.md`: recomienda **monolito modular hexagonal con eventos** para todo lo nuevo (`src/Market/<Contexto>/{Domain,Application,Infrastructure}`, eventos de dominio por Messenger con transporte Doctrine como outbox, regla de capas en CI), sin microservicios salvo la frontera física del VPS geo y la app del mensajero, y sin migrar comunicaciones. Pendiente de que el usuario acepte (ver `por-definir.md` 28 y 29).

### 3.12 Mejoras para comunicaciones derivadas de este plan

Ver `docs/mejoras-comunicaciones.md`: lo que el mercado obliga a tocar (outbox, ramas por subtipo, dominios de proveedor, `area`, interruptor por cuenta), la deuda que ya duele (`CommunicationSaleService`, `CommonService`, serialización manual, pocas pruebas con BD, catálogo en memoria), lo funcional que el mercado abarata (autorecarga, notificaciones multivía, webhooks, unidades organizativas, precios por cliente, aprendizaje e IA) y seguridad (`Referer`, login por usuario, Redis, tokens en logs).

## 4. Cómo se relacionan (dependencias)

- **A** es la base de todo lo demás.
- **C** y **D** necesitan los almacenes; **D** necesita además los municipios y los productos con peso o volumen (**A**). El stock de combos (**C**) necesita la composición del combo (**F**), así que el armado de combos se habilita con el hito 4.
- **E** necesita **A**; el precio de entrega de **D** se suma al precio de cliente de **E** en el total del pedido.
- **B** decide quién despacha; para el proveedor propio necesita **C**.
- **F** necesita **A**, **E** y, para marcar «entregable», **D**.
- **G** necesita **F**, **B**, **C**, **D** y **E**.
- **H** necesita **G** y **D**.
- **I** necesita **A** y **E**, y sus ventas necesitan **G**. La unidad organizativa y su centro de costo (**I**) se crean desde el hito 1, porque vendedores y almacenes ya se imputan a ellos; la liquidación y la facturación entre centros llegan después.
- **L** necesita **H** (eventos y hora estimada por parada) y llega a «tiempo real» de verdad con **K** (posición del transportista); antes, el seguimiento es por estados de parada.
- **N** empieza a **registrar desde el hito 4** (cada venta, armado, entrega y sugerencia deja su rastro), aunque el tablero de notas y la realimentación lleguen en el hito 8: sin datos acumulados no hay aprendizaje. **M** se apoya en **N**.
- El armado automático de órdenes (**H**, T24) usa carga, volumen y velocidad del vehículo desde el hito 5 sin IA (algoritmo determinista); la IA (**M**) lo mejora después.
- **M** necesita datos de **C** (stock y movimientos), **F** (composición y ventas) y **H** (órdenes y rutas); por eso llega al final, pero el registro de uso por cliente y los permisos se diseñan con la configuración de la unidad organizativa (**I**).
- **K** necesita **H**, pero **H** debe prepararla desde el principio: etiqueta con QR por bulto al preparar el pedido, estados de parada que pueda cambiar el transportista, evidencias como archivos adjuntos a la parada y una API pensada para la app (no solo para el dashboard).

## 5. Ruta propuesta, en hitos

Cada hito termina con algo usable. Las duraciones son orientativas e inferidas, no compromisos; se afinarán al detallar cada hito.

| Hito | Contenido | Resultado | Semanas |
|---|---|---|---|
| **0 · Preparación** | pruebas cortas, decisiones, cambios compartidos con comunicaciones y flag por cuenta | riesgos medidos y lo compartido listo y desplegado sin efecto visible | 2-3 |
| **1 · Catálogo administrable** | A | un administrador gestiona vendedores, marcas, categorías y productos | 3-4 |
| **2 · Inventario y distribución** | C + D | almacenes con stock y reservas, municipios, cobertura, tarifa por peso o volumen con mínimo y máximo, y selección de almacén | 5-6 |
| **3 · Proveedores y precios** | B + E (versión mínima) | `OWN` y routing por `area`; precio del vendedor, ajuste de plataforma y precio por cliente | 4-5 |
| **4 · Oferta y venta** | F + G | paquetes de mercado visibles para las cuentas habilitadas y venta completa con entrega manual | 7-8 |
| **5 · Entrega** | H + L (por estados) | geocodificación, transportistas, órdenes de entrega, ruta óptima, pruebas de entrega; aviso de salida con hora estimada y enlace de seguimiento por estados | 7 |
| **6 · Vendedores y terceros** | I + B completo | portal del vendedor, liquidación, primer proveedor tercero, renombrado del enum de proveedores | 7+ |
| **8 · Asistencia con IA y aprendizaje** | M + N | sugerencias de armado de combos y optimización de viajes con permisos por cliente y registro de uso; tablero de notas de mejora y realimentación de parámetros; facturación cuando se decida | 5+ |
| **7 · App del transportista** | K + L (tiempo real) | app móvil del transportista sobre la API de entregas, y con ella el seguimiento con posición en tiempo real; hasta entonces el despachador registra estados y evidencias desde el dashboard | 6+ |
| **Continuo** | J | seguridad, observabilidad, despliegue gradual, endurecimiento | — |

**Primer valor para el cliente: hitos 0 a 4** (~22-27 semanas, inferido). El total ronda las 37 semanas. Una revisión independiente sugería además un recorte más agresivo para llegar antes (categorías a un nivel, sin routing por `area`, un almacén); se puede aplicar si hace falta, a costa de rehacer piezas después.

### 5.1 Hito 0 · Preparación

Detalle en `docs/shop/hito-0-preparacion.md`. Objetivo: medir los riesgos y dejar listo lo que se comparte con comunicaciones, antes de construir mercado.

1. **Pruebas cortas** (unos días cada una, con resultado escrito):
   - **Geocodificación**: 100 direcciones reales; cuántas salen bien. Decide si la ruta óptima se automatiza o empieza como agrupación por zona con orden manual.
   - **Motor de rutas**: viabilidad de OSM autoalojado con los datos de Cuba y su licencia.
   - **Informes y listados de ventas**: cuáles suman todas las ventas y se contaminarían con mercado.
   - **ApiWebComremit**: qué consume hoy del catálogo y qué asume de su forma.
2. **Decisiones del primer grupo** (§6): identidad y acceso del vendedor, vendedor vs marca, `sku`, categorías y listas cerradas.
3. **Preparar lo compartido** con pruebas de caracterización y en cambios pequeños, desplegados a producción sin efecto visible: `area` en la clave de categoría y en el routing, proveedor `OWN` fuera de la lista de comunicaciones, ramas explícitas por subtipo de venta.
4. **Interruptor de mercado por cuenta**: oculta todo lo de mercado a los clientes de recargas y sirve de despliegue gradual.
5. **Dinero**: fijar cómo se representa en el módulo (decimal o unidades menores, no `float`).
6. **En paralelo, negocio y legal**: abastecimiento, aduanas, IVA, devoluciones y datos de destinatarios; pueden bloquear el lanzamiento aunque el código esté listo.

### 5.2 Piloto

El primer lanzamiento (tras el hito 4) se limita a: un solo vendedor (la plataforma), proveedor `OWN`, pocos municipios, cuentas habilitadas por el interruptor y entrega manual. **No incluye** portal de vendedores, terceros, órdenes de entrega ni ruta automática. El piloto sirve para validar demanda y operación antes de invertir en los hitos 5 y 6. Lo que sí se deja desde el primer día porque es barato ahora y caro después: el vendedor en cada producto (`seller_id`) y el interruptor por cuenta.

### 5.3 Reglas de alcance

- El alcance de cada hito se congela al empezarlo; los requisitos nuevos se apuntan al siguiente.
- Precios simples al principio (precio del vendedor, un porcentaje global de plataforma y un precio explícito por cliente); márgenes por vendedor o categoría y porcentajes por cliente, después.
- Cobertura y tarifa: una tarifa por unidad y un mínimo y máximo por almacén y municipio desde el inicio; tarifas distintas por categoría o por cliente, después.

## 6. Decisiones abiertas, agrupadas por cuándo hay que cerrarlas

**Antes de empezar (hito 0 / 1)**
- **Unidad organizativa y centro de costo (T22)**: qué es exactamente (¿el `Client` actual para los compradores; una entidad nueva para vendedores y socios?), si una misma empresa puede ser a la vez vendedor, socio de almacén y comprador bajo una sola unidad, y qué se imputa a cada centro (costos de suministro, armado, traslados, entregas, comisiones, liquidaciones).
- **Identidad y acceso de vendedores, transportistas y socios de almacén**: los tres tendrán usuario en el sistema, colgando de su unidad organizativa, y deben ver solo lo suyo. Hoy no hay aislamiento por usuario externo ni Voters, y dentro del dashboard actual un usuario con el rol de administración vería todo. Conviene un único mecanismo (rol y contexto por tipo de usuario externo) decidido antes de empezar.
- Vendedor y marca: ¿entidades separadas o una sola? Vendedor de plataforma como un registro propio.
- Identidad del producto (`sku` único global), profundidad de categorías y valores iniciales de servicio, subservicio y `area`.

**Antes del hito 2**
- Almacenes: uno o varios desde el inicio; si un pedido puede repartirse entre almacenes.
- Tarifa de entrega: unidades admitidas (g, kg, oz, lb…); si un pedido con productos por peso y por volumen suma dos partes o usa una sola base; si además existe un monto mínimo de pedido; moneda de las tarifas; qué hacer con un producto sin peso o dimensiones; descuentos por cliente y si la ve el vendedor.
- Fuente de la lista oficial de municipios.

**Antes del hito 3**
- Ajuste de plataforma (margen global, por vendedor o por categoría, o comisión), precio por cliente (por `Client`, no por `Account`), precio de un paquete (propio o suma de sus productos) y moneda del dinero.
- Primer proveedor tercero y cómo se obtiene su stock (en vivo, copia o sin límite).

**Antes del hito 4**
- Visibilidad de mercado por cuenta (un interruptor que también sirve de despliegue gradual) y ruta de lectura.
- Imágenes: dónde se almacenan (almacenamiento de objetos propio o volumen detrás del proxy), tamaños que se generan, límite por imagen y por combo, si los productos también tienen imágenes reutilizables en los combos, y CDN o caché delante.
- Orden de compra (T19): identificador de la orden que da el comprador, relación con el `clientTransactionId` de cada venta, qué estado tiene la orden cuando sus ventas van en entregas distintas, y si el saldo se reserva por orden o por venta; semántica de idempotencia (¿409 o 200 con la orden existente?); estado de una venta cuya reserva caduca.
- Traslados (T20): qué lleva la ficha de costo (costo de suministro de cada unidad, transporte, manipulación, merma), entre qué entidades se factura (almacén propio ↔ socio, o entre sociedades del grupo), quién transporta el traslado (un transportista del sistema con su lista de precios) y si las reservas activas impiden trasladar.
- Stock de combos (T18): ¿el armado es una operación manual del almacén (propuesta) o automático al publicar o al vender? ¿Un combo de un solo producto se arma también o usa el stock del producto? ¿Se puede desarmar un combo con reservas? Con stock propio, un combo es una unidad física: no puede repartirse entre almacenes ni envíos (afecta a la pregunta 11 de `por-definir.md`).

**Antes del hito 5**
- Motor de rutas y fuente de las coordenadas: **los fija el plan de geolocalización** (OSM autoalojado con Valhalla como candidato, geocodificación propia por intersecciones y memoria de direcciones, puerta de cumplimiento para proveedores comerciales). Quedan aquí: cómo se forman las órdenes (sugeridas y confirmadas, manuales o automáticas), ventanas horarias, transportista (entidad del despachador o con acceso propio) y prueba de entrega; hora estimada sin ruta automática (el adaptador `Zone` da ETA por etapas); enlace de seguimiento sin coordenadas.
- Lista de precios del transportista: por unidad de medida (peso o volumen) y **según el régimen del vehículo** (propio, arrendado a la plataforma, de la plataforma): ¿tres listas base con ajustes por transportista, o una lista por transportista? ¿También por municipio o zona y por parada? ¿Se liquida por orden de entrega o por periodo? ¿Qué pasa con una entrega fallida o devuelta? ¿Quién paga combustible y mantenimiento en cada régimen?
- Datos del transportista y del vehículo: qué se guarda (documento, licencia, contacto, matrícula, tipo, capacidad en volumen y peso, vigencia de permisos) y quién los mantiene (el despachador o el propio transportista desde su acceso).

**Antes del hito 6**
- Dónde vive el portal del vendedor y qué ve; liquidación a vendedores y a transportistas.

- Tipos de comprador: tercero integrado por API (cuenta de la API, recibe eventos por webhook o consulta de estado, y reenvía a su cliente si quiere) y cliente final directo (si algún día vendemos sin intermediario). El receptor lo aporta el comprador en la venta (nombre, teléfono, dirección, referencias) y es a quien avisamos de la entrega si el comprador no lo hace él.
- Notificación al comprador y al receptor: vías posibles (SMS, WhatsApp, correo, aviso dentro de la app del consumidor, webhook a ApiWebComremit para que avise él) y cuáles aplican a cada destinatario; el diseño debe admitir varias vías por aviso y por persona, con registro de qué se envió, por dónde y si llegó. Afecta a qué datos de contacto guardamos de cada uno (el comprador ya lo conoce el consumidor; del receptor solo tenemos la instantánea de la venta) y a si hacen falta webhooks salientes desde el hito 5. El «cómo» se decide más adelante.
- Hora estimada: la calcula la ruta óptima por parada; sin ruta (entrega manual), ¿franja fija por municipio o la pone el despachador?
- Seguimiento: enlace público con token por pedido (sin registro), qué muestra en cada estado y durante cuánto tiempo está activo; qué significa «tiempo real» antes de la app (por estados) y con ella (posición cada N segundos, solo mientras es la siguiente parada o toda la ruta).

**Antes del hito 8 (IA)**
- Qué funciones (armado de combos, optimización de viajes, otras), con qué modelo o proveedor, qué datos se le envían (nunca datos personales de destinatarios ni costos de otros clientes), y cómo se mide y factura el uso (por llamada, por sugerencia aplicada, cuota mensual, incluido en el plan).
- Permisos por cliente: quién los administra (la administración de cada cliente o unidad organizativa), granularidad (por función) y límites.
- Las sugerencias son propuestas: siempre las confirma una persona y queda registrado quién y qué se aplicó.
- Aprendizaje continuo (T25): qué se registra y con qué retención, quién ve las notas de mejora (plataforma, cada unidad solo las suyas), con qué periodicidad se generan, y qué parámetros se ajustan solos (tiempos por parada, velocidad real por zona) frente a los que solo se proponen.

**Antes del hito 7 (pero decididas en el hito 5 lo que condiciona la API)**
- Qué lleva el QR del paquete: un identificador opaco que solo el sistema resuelve (recomendado), nunca datos del destinatario en claro; un QR por bulto cuando un pedido va en varios.
- Qué datos del destinatario ve el transportista y cuándo: solo los de la siguiente parada mientras está en ruta (nombre, teléfono, dirección, referencias), nada del resto hasta llegar.
- Evidencias: qué se admite (foto del paquete entregado, firma, documento de quien recibe), tamaño máximo, dónde se guardan y cuánto tiempo.
- Plataforma de la app: **decidida en el plan de geolocalización: nativa (Expo)**, porque una web instalable no recibe GPS en segundo plano. Trabajo sin conexión: la ruta y las paradas se descargan al salir, los estados, evidencias y posiciones se guardan en el teléfono y se sincronizan por lotes cuando hay red.
- Qué puede cambiar el transportista por sí mismo (estado y evidencias) y qué no (orden de las paradas, datos del pedido).

**Negocio y legal (en paralelo)**
- Quién surte y entrega, requisitos legales (alimentos importados, aduanas, IVA, devoluciones, datos de destinatarios) y propiedad de la libreta de destinatarios.

## 7. Riesgos transversales

1. **Compartir catálogo y tabla de ventas con comunicaciones**: cualquier consulta, resolver o tarea que lea paquetes o ventas sin distinguir tipo puede mezclar mercado con recargas, o romperse. Es el mayor riesgo técnico y exige pruebas de regresión en cada hito.
2. **Generalizar proveedores** sin alterar pantallas, tareas programadas ni routing de comunicaciones.
3. **`area` en la clave de categoría y en el routing**: toca comparadores y claves existentes.
4. **Multi-vendedor**: aislamiento entre vendedores y confidencialidad del costo y del ajuste de plataforma.
5. **Stock y saldo**: reservas que caducan deben liberar stock y saldo en una sola transacción; sobreventa.
6. **Distribución**: varios almacenes con tarifas distintas para el mismo municipio (hay que fijar cuál se elige y cuál se muestra); coordenadas poco fiables en direcciones cubanas; disponibilidad y licencia del motor de rutas; conectividad irregular en ruta (la app debe funcionar sin conexión; el seguimiento muestra la última posición conocida y su hora); datos personales del destinatario en el teléfono del transportista (minimizar y borrar al cerrar la parada); posición del transportista expuesta al cliente final (mostrarla solo mientras es la siguiente parada y nunca su identidad completa).
7. **Alcance**: el plan creció varias veces; conviene congelar el contrato con ApiWebComremit hito a hito, no de golpe.
8. **Pérdida silenciosa de datos en formularios** (incidente ya vivido): prueba de ida y vuelta en cada pantalla.

## 8. Cómo seguimos

Lista de trabajo de la próxima sesión: `docs/shop/por-definir.md`.

1. Revisar este mapa y los hitos; corregir lo que no refleje tu idea.
2. Detallar el hito 0 (pruebas cortas, decisiones del primer grupo, preparación de lo compartido, interruptor por cuenta) y ejecutarlo.
3. Con sus resultados, detallar el hito 1 (modelo, endpoints, pruebas, pantallas y orden de PRs), usando como material los borradores y los mockups.
4. Repetir hito a hito, con una revisión independiente cuando el hito toque código compartido con comunicaciones.

## 9. Estado de la implementación del hito 1 (actualizado el 2026-10-17)

Todo lo siguiente está fusionado en `develop`, y en staging salvo lo marcado. Cada pieza lleva su pantalla de dashboard y sus pruebas (reglas 11 y 12 de `CLAUDE.md`). El epic del hito (COMR-87) está «En curso»: solo falta que se valide en staging COMR-284.

| Pieza | Historia | Estado |
|---|---|---|
| Clientes como unidad organizativa compradora (`OrgUnit`), registro fiscal por país, centros de costo | COMR-272, 270, 271, 103 (A y B) | hecho, en staging |
| Vendedores (`seller_id`, vendedor de plataforma protegido) | COMR-103 (C) | hecho, en staging |
| Producto base con internacionalización, estados y gift cards propias; el tipo lo dan servicio, subservicio y área | COMR-273, 275 (clasificación), 276 | hecho; las gift cards (emisión individual y por rango, canje por PIN, diseño SVG en el catálogo) están en staging |
| Marcas (de un vendedor, inmutable) y categorías en árbol de tres niveles | COMR-70 | hecho (backend #240 y #241, dashboard #108 y #109) |
| Menú «Mercado» (Catálogo, Organización y Operación), pantalla única «Vendedores y marcas» y categorías como el mockup 5 | COMR-70, 191, 192 | hecho |
| Producto con marca y categorías, listado con filtros, paginación y miniatura, y página del producto como los tableros 1 y 2 | COMR-70, 188, 189, 190 | hecho |
| Galería de imágenes de los productos: pestaña «Imágenes», tamaños generados y límites configurables | COMR-274, 105 | hecho (backend #244, dashboard #113) |
| Relevancia de productos en las búsquedas: nivel 0 a 3 con vigencia por días, tarjeta en la ficha, columna y filtro «Solo relevantes vigentes» | COMR-269 | hecho la marca y su administración; **el orden del catálogo del comprador aún no la usa** (ver más abajo) |
| Un único endpoint de catálogo para el comprador: `/communication/packages` incluye los productos del mercado (oferta con precio provisional, `market.*` con textos, peso, imágenes, marca, categorías y relevancia) | COMR-275 | hecho |
| Sku sugerido y slugs sugeridos al crear | COMR-277 | hecho |
| Contadores por categoría, marca y vendedor, y aviso al borrar con productos | COMR-278, 280 | hecho |
| Unidades de peso y volumen frente a T26 (base kg y l, configurable) | COMR-281 (y COMR-104, 141) | hecho |
| Textos de la pantalla de productos sobre el catálogo del comprador | COMR-282 | hecho |
| Marca y categorías de los productos que ya existían | COMR-283 | hecho (por el equipo, 2026-10-17) |
| Atributos libres del producto: pares nombre-valor con texto por idioma (es obligatorio, en opcional), hasta 20, sin nombres repetidos, y `market.attributes` en el catálogo del comprador | COMR-284 | hecho; **en «Pruebas (staging)»** |

**Lo que falta del hito 1**
1. **Validar en staging COMR-284** (atributos). Es lo único del epic que no está en «Listo».
2. **Orden por relevancia en el catálogo del comprador.** Verificado el 2026-10-17: `/communication/packages` y la vista previa del comprador ordenan solo por id (o por precio con `orderBy[price]`); la relevancia se publica en `market.relevance` pero no se aplica al orden. El criterio de COMR-269 («un producto con relevancia vigente aparece antes que uno sin ella en el orden por defecto») no se cumple todavía, y el texto de la pantalla («solo cuenta en el orden por defecto del catálogo del comprador») promete más de lo que hace. Falta decidir cómo convive con el orden por defecto de comunicaciones, que no se toca (hay consumidores que leen la primera página en orden ascendente).
3. **Exportar el listado de productos (COMR-279): fuera del hito 1** (decidido el 2026-10-17: será lo último que se haga). Sigue sin epic; el formato y las columnas están por confirmar (pregunta 33).
4. Quedan fuera a propósito del hito 1: la columna «Stock propio» del listado (el stock existe ya desde el hito 2, falta enlazarla) y las entradas del mockup «Paquetes de mercado», Proveedores y Precios (hito 3).

**Propuestas de pantalla** para las HU sin tablero (COMR-269, 275 y 276): tableros **P1, P2 y P3** del lienzo (texto de apoyo en `docs/shop/propuestas-pantallas-pendientes.md`), pendientes de aprobación antes de construir.

**Galería de imágenes (COMR-274):** fusionada en `develop` (backend #244, dashboard #113): pestaña «Imágenes» del producto con subida, referencia, orden y descripción; los tamaños (150, 400 y 1200 px) se generan en WebP con GD y los sirve nginx desde un volumen.

**Lección de proceso (2026-10-14):** las pantallas de categorías y de marcas se construyeron sin abrir antes el tablero correspondiente y hubo que rehacerlas. Antes de modelar o maquetar una historia se abre el tablero del lienzo que la ilustra, además de leer este plan.

