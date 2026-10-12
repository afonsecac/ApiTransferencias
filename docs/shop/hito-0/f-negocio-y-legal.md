# F · Negocio y legal: bloqueos de lanzamiento

Historia COMR-102 · Hito 0, bloque F · lista creada el 2026-10-12.

No es trabajo de código, pero **puede impedir el lanzamiento aunque el código esté listo**. Cada punto tiene su bloqueo, la fecha límite expresada como «antes de empezar el hito N» (aún no hay calendario fijo) y un responsable **por asignar**.

> **Para cerrar el hito 0 basta con que esta lista exista y tenga responsable y fecha por punto.** Los puntos siguen abiertos como bloqueos del lanzamiento, no del código. Falta rellenar la columna «Responsable».

| # | Tema | Qué hay que resolver | Bloquea | Antes de empezar | Responsable |
|---|---|---|---|---|---|
| 1 | Quién surte y entrega | Almacén propio o socios, en qué municipios al inicio y con qué SLA | El alcance geográfico y el tiempo de entrega prometido al comprador | Hito 2 (inventario y cobertura) | *Por asignar* |
| 2 | Alimentos importados, aduanas, IVA y facturación de productos físicos | Régimen de importación, tratamiento fiscal y qué documento se emite | Poder vender productos físicos y facturarlos | Hito 4 (venta) | *Por asignar* |
| 3 | Devoluciones y reembolsos | Política, plazos, quién asume el coste y cómo se devuelve el dinero | La venta (hito 4) y la entrega fallida (hito 5) | Hito 4 | *Por asignar* |
| 4 | Datos de destinatarios | Minimización, retención y quién es el responsable del tratamiento. Relacionado con la identidad externa (COMR-170) y con la libreta de destinatarios, que se mantiene en ApiWebComremit (aquí solo hay instantánea en la venta) | Guardar datos personales de terceros sin base legal clara | Hito 4 | *Por asignar* |
| 5 | Primer proveedor tercero | Si lo hay, quién es y su contrato | Ampliar la oferta más allá del proveedor propio (`OWN`) | Hito 3 (precios) | *Por asignar* |
| 6 | Acuerdos con vendedores | Comisión o margen, liquidación y qué pueden ver | Dar de alta vendedores externos y el ajuste de plataforma (hito 3) | Hito 3 | *Por asignar* |
| 7 | Acuerdos con transportistas | Lista de precios por unidad de medida (peso o volumen) **según el régimen del vehículo** (propio, arrendado a la plataforma, de la plataforma), por zona o parada; quién asume combustible y mantenimiento; cómo se liquidan las entregas fallidas; qué datos personales y del vehículo se exigen y cómo se custodian | La entrega (hito 5) y la liquidación (hito 6) | Hito 5 | *Por asignar* |

## Cómo se cierra cada punto

Un punto se da por resuelto cuando hay una respuesta escrita (acuerdo, política o documento legal) y su responsable la ha confirmado. Hasta entonces el lanzamiento comercial del mercado no debe anunciarse.

## Otros puntos legales detectados durante las pruebas del hito 0

- **Restricciones de uso en el país** de los servicios de mapas y rutas autoalojados (OSRM, VROOM, Nominatim y datos de OpenStreetMap): no verificadas. Ver `a2-motor-de-rutas.md`.
- **Atribución de OpenStreetMap** (ODbL): hay que mostrar «© OpenStreetMap contributors» donde se enseñen mapas o resultados derivados.
