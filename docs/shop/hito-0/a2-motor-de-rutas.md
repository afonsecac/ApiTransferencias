# A2 · Motor de rutas autoalojado

Historia COMR-97 · Hito 0, bloque A · fecha de la prueba: 2026-10-12.

## Pregunta

¿Funciona un motor de rutas autoalojado con los datos de Cuba, con qué licencia y con qué esfuerzo?

## Cómo se hizo

- **Datos:** extracto de Cuba de Geofabrik (`cuba-latest.osm.pbf`, 62 MB).
- **Motor de rutas:** OSRM (`osrm-backend`, perfil `car`, algoritmo MLD) en Docker.
- **Ordenación de paradas:** VROOM (`vroom-docker` v1.14.0) apoyado en OSRM.
- **Escenarios:** una ruta de **10 paradas en La Habana** y otra de **10 paradas en Santiago de Cuba** (capital de provincia), con una furgoneta que sale y vuelve al almacén. Las paradas son lugares públicos conocidos con coordenadas aproximadas, sin datos personales.

Los scripts están en `docs/shop/hito-0/pruebas/a2_rutas.py`.

## Resultados

| | La Habana | Santiago de Cuba |
|---|---|---|
| Paradas ajustadas a una calle (distancia media / máxima) | 39 m / 140 m | 89 m / 408 m |
| Distancia total de la ruta ordenada | 57,0 km | 55,5 km |
| Duración total estimada | 88,8 min | 95,3 min |
| Tiempo de VROOM en ordenar las paradas | 0,22 s | 0,10 s |
| Tiempo de OSRM en calcular la ruta | 0,03 s | 0,02 s |
| Paradas sin asignar | 0 | 0 |

- **Instalación:** preproceso del extracto de Cuba en **25 segundos** (extraer, particionar y personalizar) y 220 MB de memoria en servicio; descargar imágenes y datos y dejarlo funcionando fue cuestión de minutos, muy por debajo del umbral de «menos de un día».
- **Rutas razonables:** las dos siguen calles reales; por ejemplo, Capitolio → Plaza de la Revolución sale en 3,7 km y 6,6 min.
- Las distancias de ajuste a la calle reflejan sobre todo que las coordenadas de las paradas de la prueba son aproximadas, no un defecto del motor.

## Licencias

| Componente | Licencia |
|---|---|
| OSRM | BSD 2-Clause |
| VROOM y vroom-express | BSD 2-Clause |
| Datos de OpenStreetMap | ODbL 1.0: atribución obligatoria («© OpenStreetMap contributors») y compartir igual las bases derivadas |

**No verificado:** cualquier restricción de uso de estos servicios o datos en el país o por normativa de exportación. Eso no es técnico y debe revisarlo quien lleve lo legal (ver F, COMR-102).

## Límites de la prueba

- Es una **ruta ideal**: no incluye tráfico, estado real de las calles, ventanas horarias, capacidad del vehículo ni restricciones del transportista. El perfil `car` supone calles transitables en buen estado.
- Solo se probó con 10 paradas por ciudad. VROOM escala bien con cientos, pero no se midió.
- El mapa de Cuba en OSM puede tener calles mal clasificadas o cortes que el motor no conoce; conviene validar rutas reales con el transportista.

## Decisión que provoca (D30, motor de rutas)

**Recomendación: OSRM + VROOM autoalojados con datos de OSM.** Cumple el umbral del plan (instalación en menos de un día, rutas razonables en dos ciudades), es de licencia permisiva y deja los datos dentro de la plataforma. Como respaldo simple, si el motor no estuviera disponible, ordenar las paradas por orden manual o por distancia en línea recta.

Siguiente paso técnico (hito 5): empaquetarlo como servicio separado y sin estado (es el motor de geolocalización que el ADR-001 ya sitúa fuera del monolito) y actualizar el extracto de forma periódica.
