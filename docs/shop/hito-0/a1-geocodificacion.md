# A1 · Geocodificación de direcciones cubanas

Historia COMR-96 · Hito 0, bloque A · fecha de la prueba: 2026-10-12.

## Pregunta

¿Qué porcentaje de direcciones cubanas se geocodifica bien de forma automática con herramientas basadas en OpenStreetMap?

## Cómo se hizo

- **Motor:** Nominatim 4.4.1 autoalojado (`mediagis/nominatim:4.4`) con el extracto de Cuba de Geofabrik (62 MB, descargado el 2026-10-12). La importación completa tardó unos 3 minutos y usó 1,2 GB de memoria; cada consulta tarda unos 20 ms (mediana) y 40 ms (p95).
- **Direcciones:** 100 direcciones **verosímiles**, sin datos personales ni extraídas de producción: calles reales de cada ciudad con números y «entre calles» inventados. Reparto: 50 en La Habana (10 municipios), 17 en Santiago de Cuba, 17 en Camagüey y 16 en Holguín.
- **Consultas probadas:** (1) la dirección completa con `#` y «entre … y …»; (2) calle + municipio + provincia; (3) nombre de la calle **sin** el prefijo «Calle/Avenida/Calzada» + municipio + provincia; (4) nombre sin prefijo + provincia.
- **Clasificación de cada resultado:** calle y zona correctas, calle correcta con zona dudosa, zona correcta pero calle no, solo provincia, errónea o sin resultado. Una calle se da por encontrada si su nombre aparece en el nombre completo del resultado y el resultado es una vía.

Los scripts están en `docs/shop/hito-0/pruebas/` y se pueden repetir.

## Resultados

| Forma de consultar | Calle encontrada | Zona correcta o mejor | Sin resultado |
|---|---|---|---|
| Dirección completa (`Calle 23 #1055 entre 10 y 12, Vedado, …`) | **0 / 100** | 0 / 100 | 100 |
| Calle + municipio + provincia, con prefijo | 60 / 100 | 67 / 100 | 33 |
| **Nombre sin prefijo + municipio + provincia** | **74 / 100** | **98 / 100** | 2 |
| **Nombre sin prefijo + provincia** | **79 / 100** | 96 / 100 | 0 |

Por provincia, con «nombre sin prefijo + provincia»: La Habana 38/50, Santiago de Cuba 13/17, Camagüey 14/17, Holguín 14/16.

## Lo que se aprendió

1. **La dirección completa no funciona.** El `#`, el número y el «entre … y …» hacen que Nominatim no devuelva nada. Hay que **descomponer la dirección** y consultar solo por calle, municipio y provincia.
2. **El prefijo «Calle» perjudica.** En el mapa las vías llevan su nombre oficial y a menudo el coloquial entre paréntesis: «José María Heredia (Catedral)», «José Antonio Saco (Enramadas)». «Calle Heredia» no devuelve nada y «Heredia» sí. Las calles numeradas o con letra («Calle 23», «Calle L») sí necesitan el prefijo.
3. **Nombres populares frente a oficiales.** Habrá calles que los clientes llamen de una forma y el mapa de otra. Hace falta una tabla de alias que se alimente con los casos reales.
4. **Cobertura desigual.** La Habana se resuelve peor con el municipio (35/50) que solo con la provincia (38/50): los límites de municipio del mapa no siempre coinciden con los que usa la gente.

## Qué NO demuestra esta prueba

- **No mide el umbral del plan (≥ 70 % «exactas», ≤ 100 m).** Lo que se mide es si se encuentra la **calle**, no el portal. El resultado de Nominatim para una calle es un punto de la vía (a veces kilómetros de largo), no la casa, y en Cuba el mapa casi no tiene números de portal. Con estos datos, el porcentaje de direcciones a ≤ 100 m del destino real sería **mucho menor** que el 74–79 % de calles encontradas.
- Las direcciones son verosímiles. Para medir la precisión real hacen falta unas **100 direcciones aportadas por operación con sus coordenadas conocidas** (por ejemplo, entregas ya hechas con pin confirmado por el transportista).

## Decisión que provoca (D31, fuente de las coordenadas)

Según los umbrales del plan, el resultado cae en la banda intermedia: **geocodificación asistida**. La recomendación es:

1. Geocodificar automáticamente por calle + municipio/provincia, con las reglas de arriba (sin prefijo, descompuesta).
2. Mostrar el resultado al despachador como pin **a confirmar o corregir**, nunca como coordenada definitiva.
3. Guardar la corrección como coordenada de la dirección del destinatario, para que la siguiente entrega a la misma dirección ya no dependa del mapa.
4. Repetir esta prueba con direcciones reales con coordenadas conocidas antes de fijar la inversión del hito 5.

Con esto la ruta automática es viable (la zona se resuelve en 96–98 % de los casos), pero el último tramo, el portal, depende del despachador o del transportista.

## Licencia y datos

Los datos son de OpenStreetMap (ODbL 1.0): exige atribución («© OpenStreetMap contributors») donde se muestren y compartir en las mismas condiciones las bases derivadas. La instancia pública de Nominatim prohíbe el uso masivo: hay que autoalojar, como aquí.
