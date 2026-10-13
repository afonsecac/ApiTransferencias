# Runbook: un exchange por transporte de Messenger (entregas dobles en RabbitMQ)

## Qué pasaba

Cada `SaleRechargeMessage`, `CheckSaleMessage`, `SalePackageMessage` y cada correo (`ForgotPassword`, `AccountActivation`, `ClientCreated`, `PromotionCreated`…) se enviaba una vez y el worker lo recibía **dos**. Medido en staging el 2026-10-13: 1 enviado, 2 recibidos.

Causa: dos pares de transportes compartían exchange.

| Exchange | Transportes | Colas enlazadas |
|---|---|---|
| `high` | `async_notifications_high`, `async_clients_high` | `notifications_send_emails`, `recharge_mobile_send` |
| `highest` | `async_send_sale_package`, `async_check_status` | `send_sale_package`, `check_status_recharge` |

El transporte AMQP de Symfony declara el exchange como **fanout** si no se indica otro tipo (`vendor/symfony/amqp-messenger/Transport/Connection.php`). Un fanout copia cada mensaje a todas sus colas e ignora las `binding_keys`. Como el worker consume las cuatro colas y el manejador se elige por la clase del mensaje, no por la cola, cada mensaje se ejecutaba dos veces. Las recargas están protegidas por `claimForSending()` y `claimForCompleting()` (atómicos), pero los manejadores de correo envían sin comprobar repeticiones.

## Qué cambia

Cada transporte tiene su **propio exchange**; los nombres de las **colas no cambian**, así que no se pierden mensajes pendientes.

| Transporte | Exchange antes | Exchange ahora | Cola |
|---|---|---|---|
| `async_notifications_high` | `high` | `high_notifications` | `notifications_send_emails` |
| `async_clients_high` | `high` | `high_clients` | `recharge_mobile_send` |
| `async_send_sale_package` | `highest` | `highest_package` | `send_sale_package` |
| `async_check_status` | `highest` | `highest_check_status` | `check_status_recharge` |
| `async_send_notification` | `medium` | `medium` (sin cambios, ya tenía una sola cola) | `send_balance_notification` |

`tests/Config/MessengerTopologyTest.php` impide que dos transportes compartan exchange o cola, y que un exchange fanout tenga más de una cola.

Se eligieron nombres nuevos y no `type: direct` sobre `high`/`highest` porque RabbitMQ rechaza redeclarar un exchange existente con otro tipo (`PRECONDITION_FAILED`) y habría que borrarlos antes de desplegar.

## Paso 1: desplegar

Nada manual. La aplicación declara los exchanges nuevos al usarlos (`auto_setup`) y enlaza cada cola al suyo.

Efecto inmediato: **los mensajes nuevos llegan a una sola cola**. Mientras no se haga el paso 2, las colas siguen además enlazadas a `high` y `highest`, que ya nadie usa para publicar, salvo los mensajes con retraso que ya estuvieran en vuelo (ver más abajo).

## Paso 2: limpiar los exchanges antiguos

Borra `high` y `highest`, y con ellos los enlaces viejos. **Las colas y sus mensajes se conservan.** Hacerlo solo cuando el paso 1 esté desplegado.

### Antes de borrar

1. **Que no haya mensajes con retraso apuntando a los exchanges antiguos.** Las colas de retraso (`delay_…`) llevan configurado como destino el exchange con el que se crearon: si se borra con mensajes dentro, RabbitMQ los descarta al caducar. Los reintentos de Messenger pueden esperar hasta 32 minutos.

   ```bash
   docker compose exec -T rabbitmq rabbitmqctl -p client-vh1 list_queues name messages | grep '^delay'
   ```

   Las que empiezan por `delay_high_` o `delay_highest_` y **no** por `delay_high_notifications_`, `delay_high_clients_`, `delay_highest_package_` o `delay_highest_check_status_` son las antiguas. Deben tener 0 mensajes. Si alguna tiene, esperar y repetir.

2. **Que los exchanges nuevos existen y cada uno tiene una sola cola:**

   ```bash
   docker compose exec -T rabbitmq rabbitmqctl -p client-vh1 list_bindings source_name destination_name | grep -E '^(high|highest|medium)'
   ```

   Debe haber una línea por cada `high_notifications`, `high_clients`, `highest_package` y `highest_check_status`. Si falta alguno, aún no se ha usado: esperar a que pase tráfico, o declararlo con `php bin/console messenger:setup-transports <transporte>`.

### Borrar

`rabbitmqctl` no tiene un comando de borrado de exchanges y la imagen no trae `curl`; se usa `eval` (probado en RabbitMQ 3.13.7, la versión de staging):

```bash
for ex in high highest; do
  docker compose exec -T rabbitmq rabbitmqctl eval "rabbit_exchange:delete(rabbit_misc:r(<<\"client-vh1\">>, exchange, <<\"$ex\">>), false, <<\"limpieza duplicados\">>)."
done
```

Cada uno responde `ok`. **Antes de hacerlo en producción, comprobar su versión de RabbitMQ** (`rabbitmqctl version`): esa función interna puede cambiar entre versiones.

### Después

```bash
docker compose exec -T rabbitmq rabbitmqctl -p client-vh1 list_exchanges name type | grep -E '^(high|highest|medium)'
docker compose exec -T rabbitmq rabbitmqctl -p client-vh1 list_bindings source_name destination_name | grep -E '^(high|highest|medium)'
```

Deben quedar `high_notifications`, `high_clients`, `highest_package`, `highest_check_status` y `medium`, **sin** `high` ni `highest`, y cada cola con un único enlace.

Para comprobar que no hay entregas dobles: con una venta nueva, en el log del worker debe haber **un** `Received message App\Message\SaleRechargeMessage` y **un** `CheckSaleMessage` por cada envío, y ya no debe aparecer `Skipping recharge N: already being processed`.

## Ensayo en local (2026-10-13)

Con un RabbitMQ local con la topología antigua, publicando un mensaje de prueba por la API de gestión:

| | Resultado |
|---|---|
| Antes: 1 mensaje a `high` | 1 en `notifications_send_emails` **y** 1 en `recharge_mobile_send` |
| Antes: 1 mensaje a `highest` | 1 en `send_sale_package` **y** 1 en `check_status_recharge` |
| Después (exchanges nuevos), cada uno de los cuatro | **1 mensaje, una sola cola** |
| Borrar `high` y `highest` con 2 mensajes ya en cola | 204 en cada borrado; los 2 mensajes se conservan; los enlaces nuevos siguen |

## Reversa

1. Volver a la versión anterior de `messenger.yaml` (los nombres antiguos) y desplegar. La aplicación vuelve a declarar `high` y `highest` y a enlazar las colas; vuelven las entregas dobles, pero nada se pierde.
2. Los exchanges nuevos quedan sin uso y se pueden borrar igual que los antiguos.

## Qué no cubre

- **Correos ya enviados duplicados:** no se pueden deshacer. Habría que revisar si los usuarios los recibieron dos veces.
- **Idempotencia de los manejadores de correo:** con una sola entrega deja de hacer falta, pero seguiría siendo una defensa útil ante otras entregas dobles. Es una historia aparte.
- **Producción:** su topología no se ha comprobado; la configuración es la misma, así que se espera el mismo problema.
