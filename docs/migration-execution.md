# Ejecución: Migración BD prod → PG18 + Replicación bidireccional 15 días

## Servidores

| Nodo | IP | Rol |
|---|---|---|
| **Nodo A** (antiguo prod) | `94.177.230.157` | Publisher principal. BD con datos reales. |
| **Nodo B** (nuevo prod)   | `31.14.138.244`  | Subscriber → futuro único prod. |

Ruta de referencia de comandos SQL detallados: `docs/replication-plan.md`

---

## Fase 0 — Diagnóstico del Nodo A ✅

**Resultado** (2026-04-26):

| Parámetro | Valor |
|---|---|
| OS | Ubuntu 20.04.6 LTS |
| PostgreSQL | **16.4** nativo (no Docker) |
| Base de datos | `db_transfers` |
| Usuario app | `user_operator` |
| Password app | `p4sSW0rdDB1$4md` |
| Puerto | 5432 (escucha en 0.0.0.0) |
| `wal_level` actual | `replica` → hay que cambiar a `logical` |
| Migraciones aplicadas | 71 (última: `Version20250322103748`) |
| Tamaño BD | 22 MB |
| Disco `/` | 88% usado (16 GB / 19 GB) |
| App | PHP 8.2 + PHP-FPM nativo (sin Docker) |

**Diferencias clave respecto al plan original:**
- Nodo A es **PG16.4 nativo** (no PG18 Docker) → Fase 1 se omite.
- PG16 soporta `origin='none'` ✅ → la replicación bidireccional con anti-loop es posible.
- El nombre de la BD en Nodo A es `db_transfers`; en Nodo B es `api_transferencias`. La replicación lógica funciona con nombres distintos: la publicación referencia la BD de origen, la suscripción aplica los cambios en la BD local del suscriptor.
- Nodo B tiene una migración adicional (`Version20260425000001`, columna `priority`). La columna extra en el suscriptor es válida en replicación lógica siempre que tenga valor DEFAULT (NULL).

---

## Fase 1 — Upgrade PG15 → PG18 en Nodo A ⏭️ OMITIDA

Nodo A ya corre **PostgreSQL 16.4**, que soporta `origin='none'` (disponible desde PG16).
No es necesario hacer upgrade antes de activar la replicación.

---

## Fase 2 — WireGuard entre los dos VPS ✅

**Resultado** (2026-04-26):

| Parámetro | Valor |
|---|---|
| Nodo A IP WireGuard | `10.8.0.1` |
| Nodo B IP WireGuard | `10.8.0.2` |
| Puerto | UDP 51820 |
| Latencia | ~18 ms (0% pérdida) |
| Handshake | Activo |
| Inicio automático | `systemctl enable wg-quick@wg0` en ambos nodos |

**Objetivo**: túnel privado 10.8.0.0/24. El puerto 5432 nunca se expone a Internet.

```
Nodo A  →  wg0: 10.8.0.1/24
Nodo B  →  wg0: 10.8.0.2/24
```

```bash
# En ambos nodos
apt install -y wireguard
wg genkey | tee /etc/wireguard/private.key | wg pubkey > /etc/wireguard/public.key

# Crear /etc/wireguard/wg0.conf en cada nodo (ver plantillas abajo)
wg-quick up wg0
systemctl enable wg-quick@wg0

# Verificar conectividad
# Desde Nodo A:
ping -c 3 10.8.0.2
# Desde Nodo B:
ping -c 3 10.8.0.1
```

**Plantilla Nodo A** (`/etc/wireguard/wg0.conf`):
```ini
[Interface]
PrivateKey = <PRIVATE_KEY_A>
Address    = 10.8.0.1/24
ListenPort = 51820

[Peer]
PublicKey  = <PUBLIC_KEY_B>
AllowedIPs = 10.8.0.2/32
Endpoint   = 31.14.138.244:51820
```

**Plantilla Nodo B** (`/etc/wireguard/wg0.conf`):
```ini
[Interface]
PrivateKey = <PRIVATE_KEY_B>
Address    = 10.8.0.2/24
ListenPort = 51820

[Peer]
PublicKey  = <PUBLIC_KEY_A>
AllowedIPs = 10.8.0.1/32
Endpoint   = 94.177.230.157:51820
```

**Criterio de avance**: `ping 10.8.0.2` desde Nodo A responde. `ping 10.8.0.1` desde Nodo B responde.

---

## Fase 3 — Habilitar `wal_level=logical` en ambos nodos ✅

**Resultado** (2026-04-26):

| Nodo | wal_level | max_wal_senders | max_replication_slots | track_commit_timestamp |
|---|---|---|---|---|
| Nodo A (PG16.4 nativo) | `logical` | 10 | 10 | `on` |
| Nodo B (PG18 Docker) | `logical` | 10 | 10 | `on` |

- **Nodo A**: `ALTER SYSTEM` + `systemctl restart postgresql@16-main`
- **Nodo B**: se creó `docker-compose.vps.replication.yaml` (overlay con `-c wal_level=logical …`) y se recreó el contenedor con `--force-recreate database`.

**Objetivo**: Postgres acepta replicación lógica. Requiere restart breve (~30 s).

Crear o actualizar en cada nodo el overlay `docker-compose.vps.replication.yaml`:

```yaml
services:
  database:
    command: >
      postgres
      -c wal_level=logical
      -c max_wal_senders=10
      -c max_replication_slots=10
      -c max_logical_replication_workers=8
      -c track_commit_timestamp=on
```

```bash
# En cada nodo (uno a la vez para minimizar downtime)
docker compose -f docker-compose.vps.yaml -f docker-compose.vps.prod.yaml \
  -f docker-compose.vps.replication.yaml --env-file .env.vps \
  up -d --force-recreate database

# Verificar
docker exec <container_db> psql -U $POSTGRES_USER -c "SHOW wal_level;"
# Esperado: logical
```

**Criterio de avance**: `wal_level = logical` en ambos nodos.

---

## Fase 4 — Snapshot inicial de datos (~5 min de downtime en Nodo A) ✅

**Resultado** (2026-04-26): downtime real = 30 s.

| Tabla | Filas Nodo A | Filas Nodo B post-restore |
|---|---|---|
| communication_sale_history | 10 072 | 10 072 |
| communication_sale_info | 4 338 | 4 338 |
| balance_operation | 4 251 | 4 251 |
| doctrine_migration_versions | 71 | 72 (se conservó Version20260425000001) |
| Tamaño BD | 22 MB | 22 MB |

- `nginx` y `php8.2-fpm` detenidos en Nodo A → `pg_dump --data-only --exclude-table-data=doctrine_migration_versions -Fc` → descarga local → SFTP a Nodo B → TRUNCATE (excepto doctrine_migration_versions) → `pg_restore --data-only --disable-triggers` → servicios reiniciados en Nodo A.

**Objetivo**: copiar todos los datos de prod del Nodo A al Nodo B sin duplicar el schema.

```bash
# 1. Detener app en Nodo A (BD sigue corriendo)
ssh root@94.177.230.157 "docker compose ... stop php-fpm nginx worker*"

# 2. Dump data-only en Nodo A
ssh root@94.177.230.157 "docker exec <container_db> pg_dump \
  -U $POSTGRES_USER -Fc --data-only -d $POSTGRES_DB \
  > /tmp/snapshot_prod.dump"

# 3. Transferir al Nodo B vía WireGuard
ssh root@94.177.230.157 "scp /tmp/snapshot_prod.dump root@10.8.0.2:/tmp/"

# 4. En Nodo B: vaciar tablas (conservar doctrine_migration_versions)
docker exec -i <container_db> psql -U $POSTGRES_USER $POSTGRES_DB << 'SQL'
DO $$ DECLARE r record; BEGIN
  FOR r IN SELECT tablename FROM pg_tables
           WHERE schemaname = 'public'
           AND tablename <> 'doctrine_migration_versions'
  LOOP
    EXECUTE 'TRUNCATE TABLE ' || quote_ident(r.tablename) || ' RESTART IDENTITY CASCADE';
  END LOOP;
END $$;
SQL

# 5. Restaurar datos en Nodo B
docker exec -i <container_db> pg_restore \
  -U $POSTGRES_USER -d $POSTGRES_DB \
  --data-only --disable-triggers < /tmp/snapshot_prod.dump

# 6. Aplicar migración pendiente en Nodo B (si Nodo A no la tenía)
./deploy.sh prod --console 'doctrine:migrations:migrate --no-interaction'
```

**Criterio de avance**: `SELECT count(*) FROM clients;` devuelve el mismo valor en ambos nodos.

---

## Fase 5 — Crear rol, publicaciones y suscripciones ✅

**Resultado** (2026-04-26):

| Elemento | Nodo A | Nodo B |
|---|---|---|
| Rol replicator | ✅ creado | ✅ creado |
| Publicación | `pub_a` FOR ALL TABLES | `pub_b` FOR ALL TABLES |
| Suscripción | `sub_a_from_b` → pub_b (Nodo B) | `sub_b_from_a` → pub_a (Nodo A) |
| Estado slot | `streaming`, lag = 0 bytes | `streaming`, lag = 0 bytes |
| Port DB WireGuard | — (PG nativo escucha 0.0.0.0) | 10.8.0.2:5432 (overlay replicación) |

- `origin='none'` activo en ambas suscripciones → anti-loop bidireccional.
- Datos verificados iguales en 5 tablas clave post-replicación.

**Objetivo**: activar el flujo bidireccional de cambios entre los dos nodos.

Ver comandos SQL detallados en `docs/replication-plan.md` → Paso D.

Resumen:
1. Crear rol `replicator` con `REPLICATION LOGIN` en **ambos** nodos.
2. Crear `pub_a FOR ALL TABLES` en Nodo A.
3. Crear `pub_b FOR ALL TABLES` en Nodo B.
4. Crear `sub_b_from_a` en Nodo B (se suscribe a `pub_a`, `copy_data=false`, `origin='none'`).
5. Crear `sub_a_from_b` en Nodo A (se suscribe a `pub_b`, `copy_data=false`, `origin='none'`).

Autorizar la conexión en `pg_hba.conf` de cada nodo:
```
# Añadir en postgresql.conf o como volumen montado
host replication replicator 10.8.0.0/24 scram-sha-256
```

**Criterio de avance**: `SELECT * FROM pg_stat_subscription;` muestra estado `streaming` en ambos nodos.

---

## Fase 6 — Particionar secuencias (anti-colisión de PKs) ✅

**Resultado** (2026-04-26):

| Secuencia (muestra) | Nodo A nextval | Nodo B nextval |
|---|---|---|
| balance_operation_id_seq | 4677 (impar) | 4678 (par) |
| communication_sale_history_id_seq | 10489 (impar) | 10488 (par) |
| messenger_messages_id_seq | 331 (impar) | 330 (par) |

35 secuencias particionadas con `INCREMENT BY 2`. Implementado con `DO $$` dinámico para evitar errores de tablas sin columna `id`.

**Objetivo**: imposibilitar que dos inserts simultáneos en distintos nodos generen el mismo ID.

```
Nodo A → IDs IMPARES  (INCREMENT BY 2, start en siguiente impar)
Nodo B → IDs PARES    (INCREMENT BY 2, start en siguiente par)
```

Ver SQL completo en `docs/replication-plan.md` → Paso E.

> ⚠️ Regla durante toda la ventana de replicación: **prohibido ejecutar `ALTER SEQUENCE` manualmente**.

**Criterio de avance**: smoke test bidireccional (ver `docs/replication-plan.md` → sección Verificación).

---

## Fase 7 — Ventana de convivencia (15 días)

**Objetivo**: operar con doble escritura, verificar lag diariamente y estabilizar el Nodo B.

```sql
-- Monitoreo diario de lag (ejecutar en cada nodo)
SELECT slot_name, active,
       pg_size_pretty(pg_wal_lsn_diff(pg_current_wal_lsn(), confirmed_flush_lsn)) AS lag
FROM pg_replication_slots;

SELECT subname, received_lsn,
       EXTRACT(EPOCH FROM (now() - latest_end_time))::int AS lag_s
FROM pg_stat_subscription;
```

Alertar si `lag` supera 500 MB o `lag_s` supera 60 s.

> Si se detectan conflictos persistentes: degradar a unidireccional eliminando `sub_a_from_b`
> (ver `docs/replication-plan.md` → Plan de contingencia).

**Resultado día 1 (2026-04-26)**:

| Verificación | Resultado |
|---|---|
| Slots activos | `sub_b_from_a` (Nodo A) y `sub_a_from_b` (Nodo B) → `active=true`, lag=0 B |
| Lag suscripción | < 20 s |
| Conteos idénticos | sales=10072, balance=4251, products=52, promotions=66 ✅ |
| Smoke test B→A | `sys_config` insertado en Nodo B → visible en Nodo A en < 3 s ✅ |

Script de monitoreo instalado en Nodo A: `/root/repl_monitor.sh`  
Cron activo: `0 9 * * *` → log en `/var/log/repl_monitor.log`

**Nota arquitectura**: ventas/balance se generan en Nodo A (DNS apunta ahí); productos y
promociones se crean en Nodo B. `configure_sequence` no tiene riesgo de duplicado porque
`getLastSequence()` solo lo invocan los procesadores de ventas, que reciben tráfico
exclusivamente por Nodo A.

---

## Fase 8 — Cutover (apunta el tráfico al Nodo B)

**Objetivo**: Nodo B asume el 100% del tráfico. Downtime controlado < 2 min.

```bash
# 1. Congelar escrituras en Nodo A
docker exec <container_db_A> psql -U $POSTGRES_USER \
  -c "ALTER DATABASE api_transferencias SET default_transaction_read_only = on;"

# 2. Esperar lag = 0 en el slot de Nodo A
docker exec <container_db_A> psql -U $POSTGRES_USER -c \
  "SELECT slot_name, confirmed_flush_lsn = pg_current_wal_lsn() AS caught_up
   FROM pg_replication_slots;"
# caught_up debe ser TRUE

# 3. Drenar colas RabbitMQ del Nodo A (esperar a 0 mensajes)
# 4. Redirigir DNS: api.prod.comremit.com → 31.14.138.244 (ya apunta aquí)
# 5. Backup final del Nodo A
ssh root@94.177.230.157 "./deploy.sh prod --backup"
# 6. Detener Nodo A
ssh root@94.177.230.157 "./deploy.sh prod --stop"
```

**Criterio de avance**: `curl https://api.prod.comremit.com/health/ready` devuelve `{"status":"ok"}`.

---

## Fase 9 — Desmantelamiento del cluster (Nodo B queda solo)

**Objetivo**: eliminar toda traza del cluster. Nodo B queda limpio como instalación estándar.

Ver comandos completos en `docs/replication-plan.md` → Paso H.

Checklist:
- [ ] `DROP SUBSCRIPTION sub_b_from_a` en Nodo B
- [ ] `DROP PUBLICATION pub_b` en Nodo B
- [ ] Limpiar replication slots huérfanos (`pg_drop_replication_slot`)
- [ ] `DROP ROLE replicator`
- [ ] Restaurar secuencias a `INCREMENT BY 1` con `MAX(id)+1` por tabla — **NO usar `last_value`** (ejecutar `scripts/phase9-consolidate-sequences.py`; ver SQL corregido en `docs/replication-plan.md` H4)
- [ ] Bajar overlay de replicación: `./deploy.sh prod --replication down`
- [ ] Eliminar variables `REPL_*` / `SEQUENCE_*` del `.env.vps`
- [ ] `wg-quick down wg0` + `systemctl disable wg-quick@wg0`
- [ ] Verificación final: 0 publicaciones, 0 suscripciones, 0 slots, `increment_by=1`
- [ ] Eliminar Nodo A tras 30 días de operación estable

---

## Estado actual

| Fase | Estado |
|---|---|
| 0 — Diagnóstico Nodo A | ✅ Completado |
| 1 — Upgrade PG15→18 Nodo A | ⏭️ Omitido (ya es PG16.4) |
| 2 — WireGuard | ✅ Completado |
| 3 — wal_level=logical | ✅ Completado |
| 4 — Snapshot inicial | ✅ Completado |
| 5 — Publicaciones / suscripciones | ✅ Completado |
| 6 — Partición de secuencias | ✅ Completado |
| 7 — Convivencia 15 días | 🔄 En curso (inicio 2026-04-26, fin ~2026-05-11) |
| 8 — Cutover | ⏳ Pendiente |
| 9 — Desmantelamiento | ⏳ Pendiente |
