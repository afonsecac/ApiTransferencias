# Selección de producto por proveedor en Promociones

## Por qué existe

Antes de este cambio, una `CommunicationPromotions` estaba atada a **un único `CommunicationProduct`** (`CommunicationPromotions::$product`, FK obligatoria). Como `CommunicationProduct::$provider` es fijo, el producto de una promoción decidía de facto el proveedor — no había forma de decir "para esta promoción, si el proveedor termina siendo CSQ usa este producto, y si es DTOne usa este otro". El código ya dejaba constancia del hueco:

```php
// src/Provider/ProviderDispatchResolver.php
* El camino "con promoción" (mapeo explícito CommunicationPromotionProviderProduct,
* en vez de búsqueda automática por tupla) es Fase 5 — todavía no existe esa
* entidad, así que select() solo cubre venta regular.
```

Este cambio da de alta esa entidad y engancha las promociones al mismo mecanismo de despacho dinámico (prioridad + fallback) que ya usan los paquetes V2, sin migrar las promociones al catálogo V2 (`CommunicationPackage`) — siguen siendo legacy, solo que ahora el proveedor no se deriva ciegamente del producto "de origen".

## Investigación de proveedores

- **CSQ**: no tiene ningún concepto de "promoción" en su API (`csq-docs.apidog.io` — sin endpoints de promotion/bonus/discount/campaign). Es un catálogo plano de artículos con precio propio.
- **DTOne**: tiene un objeto `Promotion` (`GET /promotions/{id}`), pero es solo lectura — no se selecciona una promoción al comprar. Se selecciona un `product_id` normal y, si tiene una promo vigente, el bono aparece automáticamente en la respuesta (`benefits[].amount.promotion_bonus`).

En ambos proveedores lo único seleccionable es el **producto** — de ahí que el diseño sea un mapeo *Promoción → Producto por proveedor*, nunca un concepto de promoción a nivel de proveedor.

## El vínculo: `CommunicationPromotionProviderProduct`

Calco de `CommunicationPackageProviderProduct` (mismo patrón, misma UX): `(promotion_id, provider, product_id)`, único por `(promotion, provider)`. `provider` es un código de texto (`CommunicationProviderEnum`), no una FK — la lista de proveedores sale de `ProviderRegistry::registered()` en runtime.

**Diferencia clave con los paquetes**: no hay matching automático por tupla. Una promoción no tiene un `destinationAmount`/`destinationCurrency` único a nivel de promoción (genera tramos de precio por cliente vía `CommunicationClientPackage`), así que un match automático sobre el catálogo completo podría devolver un producto no pensado para esa promoción. Toda asociación proveedor→producto debe ser **explícita**.

## Cómo se elige el proveedor al redimir

`PromotionProviderDispatchResolver::select()` (`src/Provider/PromotionProviderDispatchResolver.php`) recorre la lista de prioridad del cliente (`ClientProviderRouting`, el mismo mecanismo que `ProviderDispatchResolver` usa para paquetes V2), y para cada proveedor candidato:

1. Si hay un vínculo explícito (`CommunicationPromotionProviderProduct`) habilitado para ese proveedor, lo usa.
2. Si no, cae al producto "de origen" de la promoción (`CommunicationPromotions::$product`) — **solo** si su proveedor coincide con el candidato evaluado. Esto es lo que garantiza que **ninguna promoción existente (sin vínculos nuevos) cambia de comportamiento**: sigue resolviendo al mismo proveedor/producto de siempre.
3. Si ninguno de los dos aplica, prueba el siguiente proveedor en la lista de prioridad.
4. Si ningún proveedor disponible tiene producto, lanza `PROMOTION_NOT_DISPATCHABLE` (409).

Mismo kill switch y fallback a proveedor único que `ProviderResolver`/`ProviderDispatchResolver` (`communications.provider.routing.enabled` + `communications.provider.default`).

## Dónde se engancha

- `CommunicationSaleService::admitLegacy()` — cuando la venta tiene promoción, llama a `PromotionProviderDispatchResolver::select()` en vez de `resolveAndGuardProvider()`. El producto y `externalRef` resueltos se congelan en `CommunicationSaleInfo::$dispatchProduct`/`$dispatchExternalRef` (las mismas columnas que V2 ya usa para paquetes — se reutilizan, no se crean nuevas).
- `CommunicationSaleService::invokeRechargeCommunication()` — al construir el `productCode` a enviar al proveedor, prefiere `$saleRecharge->getDispatchExternalRef()` cuando está poblado; si no (reservas anteriores a este cambio, o cualquier fila histórica), cae al comportamiento de siempre (`resolveProductExternalId(promotion->getProduct())`).

## CRUD del vínculo

`CommunicationPromotionBindingService` (`src/Service/Pricing/`) + endpoints en `DashboardPromotionController`:

- `GET /promotions/{id}/bindings` — una fila por proveedor registrado, con el producto vinculado (si existe) y el catálogo completo habilitado de ese proveedor como candidatos.
- `PUT /promotions/{id}/bindings/{provider}` — fija el vínculo (`SetPromotionProviderProductDto { productId }`).
- `DELETE /promotions/{id}/bindings/{provider}` — quita el vínculo.

## Regla de compatibilidad hacia atrás

Sin ningún `CommunicationPromotionProviderProduct` configurado, una promoción se comporta **exactamente igual** que antes de este cambio: el proveedor se resuelve al del producto "de origen" (paso 2 del resolver), y el `productCode` despachado es el mismo de siempre (fallback en `invokeRechargeCommunication()`). No hace falta backfill ni migrar promociones existentes — el vínculo es puramente aditivo y opcional.
