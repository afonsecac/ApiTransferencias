# Runbook: el proveedor rechaza nuestra credencial al crear una venta (COMR-265)

## Qué pasa

Si al crear una venta el proveedor responde **401 o 403** (clave caducada, rotada o sin el permiso necesario), eso habla de nuestra credencial, no de la venta, y la transacción nunca llega a crearse. Antes, DTOne lo trataba como un rechazo definitivo (la venta pasaba a `REJECTED`, se probaba el proveedor secundario y se liberaba el saldo) y ETECSA como un resultado desconocido.

Ahora, en DTOne y ETECSA (recargas y venta de paquetes):

1. La venta **no se rechaza ni cambia de proveedor**: queda en `state=PENDING`, `state_process=CREATED`, lista para reenviarse. El reenvío es seguro porque la transacción no se creó. No se programa ninguna consulta de estado.
2. El proveedor se marca como **no disponible solo para ese entorno** (`provider_availability.auto_enabled = false`, motivo «Credencial rechazada…») y se avisa a los administradores con una notificación crítica (una sola, no una por venta).
3. Mientras esté apagado, las ventas nuevas de ese proveedor y entorno quedan pendientes de envío, como ante una caída.

CSQ no se ha tocado: no distingue 401/403 a nivel HTTP en este cliente.

## Cómo se recupera

1. Reponer la credencial en la pantalla de credenciales del proveedor (o rotar la clave en el gateway).
2. El siguiente ping bueno (`PingProvidersTask`) vuelve a activar el proveedor y reencola las ventas pendientes. También sirve el interruptor manual de la pantalla de disponibilidad: activarlo reinicia el estado automático.
3. Un ping con la credencial aún mala sale «inconcluso» y no cambia nada: el proveedor sigue apagado.

## Comprobaciones (solo lectura)

```sql
-- Proveedores apagados por credencial rechazada
SELECT provider, environment_type, auto_enabled, last_action_at, last_action_reason
FROM provider_availability
WHERE auto_enabled = false AND last_action_reason LIKE 'Credencial rechazada%';

-- Ventas liberadas esperando reenvío
SELECT id, provider, state, state_process, updated_at
FROM communication_sale_info
WHERE state = 'Pending' AND state_process = 'Created' ORDER BY id;
```

## Qué no cambia

Un 400 de validación del producto o de los datos sigue siendo un rechazo definitivo (con failover si hay proveedor secundario).

## Reversa

Desplegar la versión anterior. No hay migraciones.
