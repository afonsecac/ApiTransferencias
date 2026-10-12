# Deduplicación por referencia y «no encontrada» en cada proveedor (COMR-256)

Pruebas hechas el 2026-10-12 contra el sandbox de TEST de cada proveedor, llamando a los adaptadores (`recharge()` y `fetchRechargeStatus()`) con un `transactionId` nuevo de 13 dígitos. Cada prueba envía una recarga, consulta de inmediato, reenvía la misma referencia, consulta 5 s después y consulta una referencia que no existe. Para ETECSA se añadió la comprobación del saldo antes y después de cada envío. Una sola ejecución válida por proveedor: son observaciones puntuales, no garantías contractuales.

## Resultados

| Prueba | ETECSA (`sandbox.communications.comremit.com`) | CSQ (`evsb.csqworld.com`) | DTOne (`preprod-dvs-api.dtone.com`) |
|---|---|---|---|
| Envío inicial | `ACCEPTED`. Saldo: −600 CUP | `COMPLETED`, código 10 (2230 ms) | `ACCEPTED`, `CONFIRMED` (1410 ms) |
| Consulta inmediata tras crear | `PENDING` (ya indexada); `sale-info`: `Ordered`, con `orderId` | `COMPLETED` (ya indexada, 183 ms) | `COMPLETED` (ya indexada, 222 ms) |
| **Reenvío de la misma referencia** | `REJECTED`, «Unexpected message during the sale». **Lo rechaza**, sin segundo débito (saldo +0,00 CUP) y el `orderId` no cambia | `REJECTED`, código -1, «Ticket duplicado para el mismo día/máquina». **Lo rechaza** | DTOne lo **rechaza** (400, código 1007001, «Transaction external ID has already been used») y el adaptador devuelve el estado de la transacción original: `COMPLETED`, misma referencia |
| Consulta 5 s después | `PENDING`; `sale-info` igual: `Ordered`, mismo `orderId`, `rechargeState: Pendiente` | `COMPLETED`, misma referencia de proveedor | `COMPLETED`, misma referencia de proveedor |
| Consulta de referencia inexistente | Excepción con HTTP 404 | `UNKNOWN`, código 900 «Invalid parameters» | HTTP 404 «Transaction not found» (código 1008004) → `UNKNOWN`, **no `REJECTED`** |

## Matices

- **ETECSA: la primera tanda no valía.** Dos ejecuciones previas dieron `ACCEPTED` en el reenvío y la venta acabó `Rejected` sin `orderId`: la venta nunca llegó a existir, así que el reenvío era un envío nuevo. Hay dos causas, ambas de la prueba: (1) en TEST, `EtecsaGatewayClient::recharge()` solo sustituye por `APP_TEST_PHONE` (el número que completa en sandbox) un teléfono que termina en `60`; (2) la referencia real tiene el formato `yymmdd` + tipo de venta (`01` recarga) + secuencia de 5 dígitos, y yo usaba 7 dígitos aleatorios. Con ambos corregidos, la venta queda `Ordered` y el reenvío se rechaza.
- **ETECSA: el rechazo del duplicado es un mensaje genérico.** `Unexpected message during the sale` no distingue «referencia repetida» de cualquier otro fallo, y el adaptador lo convierte en `REJECTED`. No se conoce la ventana de deduplicación (si caduca al día siguiente, como en CSQ). La pasarela no ofrece endpoint para listar ventas: solo `sale-info` por referencia y el saldo.
- **CSQ.** El rechazo es por «mismo día/máquina»: la referencia local que enviamos son los dígitos del `transactionId` sin la fecha (`deriveLocalReference`), única por día. Una referencia repetida al día siguiente no es un duplicado para CSQ. El comentario de `CsqStatusMapper` documenta que el 2026-08-10 una consulta inmediata respondió 900 por retraso de indexación; hoy respondió `COMPLETED` al instante. El retraso varía y no se puede contar con él ni en un sentido ni en el otro.
- **ETECSA y CSQ: el rechazo por duplicado llega como `REJECTED`.** Si algún camino reenviara una venta ya enviada y aplicara ese resultado, la rechazaría y liberaría el saldo, aunque el proveedor la tenga ordenada o completada. Es exactamente lo que hacía el reenvío tras un 404 que se ha retirado en COMR-257.
- **DTOne.** Es el único cuyo adaptador resuelve bien el duplicado (devuelve el estado de la transacción original). Una primera tanda dio 401 con las credenciales que había en la base local; tras reponerlas, y con un producto móvil (`35718`), funciona. El producto `35835` es de tipo cuenta (Nauta) y exige `credit_party_identifier.account_number`. El código del adaptador asume que «no encontrada» llega como `REJECTED` (listado vacío), pero con el filtro `external_id` el sandbox responde 404, que el adaptador deja como `UNKNOWN`. No se ha comprobado si producción responde igual.
- **Importe de CSQ.** El producto de prueba tiene que respetar el rango `by_range` del catálogo (Cubacel/7854: 1100 a 5500 CUP). Con 100 CUP responde `927 Importe incorrecto`.

## Decisión

1. **Nunca se reenvía una venta que ya se envió.** Los tres proveedores rechazan o resuelven el reenvío de la misma referencia, pero con límites que no conocemos del todo: CSQ solo dentro del mismo día, ETECSA con una ventana desconocida y un mensaje genérico, y DTOne por `external_id`. Reenviar solo puede aportar un doble cobro si falla el rechazo, y nada si funciona.
2. **Estado terminal ante la duda: «incierto» (`UNCERTAIN`, COMR-257).** La venta conserva el saldo reservado y se resuelve consultando al proveedor por nuestro `transactionId` o a mano, con motivo y usuario (COMR-258).
3. **Único reintento permitido: el seguro de ETECSA** (`POST /sales/{id}/retry`, modo consulta). Para el resto de proveedores el reintento manual devuelve 409.
4. **Un «no encontrada» no es un rechazo.** En el sandbox de DTOne llega como `UNKNOWN` y la transacción recién creada se indexa al instante, así que el riesgo de que `promoteToFallback` mande una venta a dos proveedores por un retraso de indexación no se ha reproducido. Aun así, un `REJECTED` por «no encontrada» no debe liberar saldo ni cambiar de proveedor sin confirmar.
5. **Un rechazo por duplicado no es un rechazo de la venta.** El resultado de un reenvío nunca se aplica a la venta original.

## Historias derivadas

- **Tratar 401 y 403 al crear como indisponibilidad del proveedor, no como rechazo de la venta.** Hoy, con DTOne, un 401 en la creación se mapea a `REJECTED` (4xx definitivo) y, con proveedor secundario, la venta pasaría a failover por un problema de nuestras credenciales.
- **No tratar el rechazo por duplicado de CSQ («Ticket duplicado») ni el de ETECSA («Unexpected message during the sale») como un rechazo de la venta.** Debe consultarse el estado de la venta original. Con ETECSA el mensaje es genérico, así que lo seguro es consultar siempre antes de aplicar un `REJECTED` a un reenvío.
- **Confirmar en producción, de solo lectura, qué responde DTOne a una referencia que no existe** (404 o listado vacío): de eso depende si «no encontrada» llega como `UNKNOWN` o como `REJECTED`.
