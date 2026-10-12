# A4 · Qué consume ApiWebComremit del catálogo

Historia COMR-99 · Hito 0, bloque A · revisado el 2026-10-12 sobre `ApiWebComremit` (rama `master`, versión 1.35.1).

## Pregunta

¿Qué campos del catálogo usa el consumidor y qué pasa si llegan valores de enumeración nuevos o campos adicionales?

## Dónde está

- Cliente HTTP: `apps/product/src/infrastructure/providers/sendmundo/sendmundo-api.client.ts` (`getPackages`, con paginación hydra completa: sigue `hydra:next` hasta agotarla, con `itemsPerPage=100`).
- Adaptador: `sendmundo-product-sync.adapter.ts` (`fetchProducts`), que normaliza cada paquete.
- Caso de uso: `apps/product/src/application/use-cases/sync-products-by-country/sync-products-by-country.use-case.ts`.
- Tipos de la respuesta: `sendmundo.types.ts` (`SendmundoPackage`, `SendmundoBenefit`).

## Qué campos usa

De cada paquete: `id`, `name`, `description`, `activeStartAt`, `activeEndAt`, `tags`, `benefits[]` (`additional_information`, `amount.{base, promotion_bonus, total_excluding_tax, total_including_tax}`, `type`, `unit`, `unit_type`, `schedule`), `service.name`, `service.subservice.name`, `destination.{amount, unit, unit_type}`, `validity`, `promotions[]`, `amount` y `currency`.

El tipo de producto se resuelve por el subservicio (`AIRTIME`, `BUNDLE`, `DATA`, `INTERNET`, `LANDLINE`, `SMS`), por la combinación `uSIM/uSIM` o, si no, por las etiquetas.

## Cómo trata lo nuevo (probado con un fixture)

Se ejecutó un spec temporal contra el adaptador real (no se ha dejado nada en ese repositorio). Resultados:

| Caso | Qué pasa |
|---|---|
| Campos adicionales en el paquete (`kind`, `items`, `area`…) | **Se ignoran sin problema**: el adaptador copia solo los campos que conoce |
| Un beneficio de tipo nuevo (`PRODUCT`), con `amount` presente y valores nulos | **Pasa**: `type`, `unit` y `unit_type` son cadenas libres sin validar; los importes nulos se convierten en 0 |
| Un paquete con subservicio que no conoce | **Se omite en silencio** con un aviso en el log; el resto se sincroniza |
| Un paquete sin `amount` o sin `currency` | Se omite con aviso |
| Un beneficio **sin** `amount` | **Falla toda la sincronización** (TypeError) |
| Un paquete **sin** `service.subservice` pero con una etiqueta conocida | **Falla toda la sincronización** (TypeError) |
| Un paquete **sin** `destination` | **Falla toda la sincronización** (TypeError) |

## Riesgos para el contrato del hito 4

1. **Tres campos son obligatorios y su ausencia tumba el catálogo entero**: `benefits[].amount`, `service.subservice` y `destination`. Un producto de mercado enviado por el mismo endpoint debe incluirlos siempre, aunque sea con valores vacíos, o debe publicarse por un endpoint distinto.
2. **Lo que el consumidor no reconoce desaparece de su catálogo.** El caso de uso desactiva primero todos los productos regulares activos del país y luego reactiva los que consigue procesar; un producto omitido (subservicio desconocido, `ProductType` inexistente) se queda desactivado. Los productos de mercado no se verían en ese consumidor, y eso es lo deseado mientras no los soporte; pero un cambio en el valor de un campo que hoy sí reconoce los haría desaparecer.
3. **La guardia contra borrado masivo es parcial.** Aborta solo si el proveedor devuelve **0** productos y había activos. Si devuelve algunos y todos se omiten en el `upsert` (por ejemplo, por un `ProductType` que no existe), no hay guardia y se desactiva todo.
4. **`benefit.type` no se valida en este repositorio**: `PRODUCT` pasa. Lo que no está verificado es cómo lo interpreta el frontend de Comremit; hay que comprobarlo antes de publicar beneficios de ese tipo.

## Lista de campos y valores

**Deben ser aditivos (no cambiar de forma ni quitarse):** `id`, `name`, `description`, `activeStartAt`, `activeEndAt`, `tags`, `benefits[].{additional_information, amount, type, unit, unit_type, schedule}`, `service.name`, `service.subservice.name`, `destination.{amount, unit, unit_type}`, `validity`, `promotions`, `amount`, `currency`.

**Se pueden añadir sin riesgo:** cualquier campo nuevo en el paquete (`kind`, `items`, `area`…) y valores nuevos de `benefit.type`, `unit` y `unit_type`.

**Romperían la sincronización:** quitar o anular `benefits[].amount`, `service.subservice` o `destination`; cambiar el significado del subservicio (el consumidor deduce de él el tipo de producto); o enviar por este endpoint paquetes de mercado que no cumplan lo anterior.

## Recomendación

- Los productos de mercado **no** salen por `/api/communication/packages` hasta que el consumidor los soporte; si salieran, deben incluir los tres campos obligatorios.
- Pedir al equipo de ApiWebComremit que (a) proteja esos tres accesos con comprobaciones, para que un paquete defectuoso se omita en lugar de tumbar el catálogo, y (b) extienda la guardia contra borrado masivo al caso de «todos omitidos». Es una mejora independiente de este proyecto y de bajo riesgo.
