# Plan: Cluster PostgreSQL bidireccional — Migración a nuevo servidor

## Contexto

Se necesita montar la plataforma ApiTransferencias en un nuevo servidor VPS y sincronizar su base de datos con la de producción actual durante un período de convivencia, de modo que los cambios que ocurran en cualquiera de los dos nodos (clientes, plataformas, transferencias) se reflejen en el otro.

**El cluster es temporal.** Una vez que toda la carga esté sobre el nuevo servidor, se hace un cutover controlado, se desmantela completamente la infraestructura de replicación y el sistema queda con una única base de datos limpia, idéntica a como estaba antes de la migración pero sobre el nuevo hardware. El objetivo final es un solo Postgres sin rastro del cluster.

**Stack actual relevante:**
- PostgreSQL 18 (`postgres:18-alpine`) en Docker Compose, sin puertos expuestos al host (solo red interna `app`), volumen `database_data`.
- PHP 8.4 + Symfony 7.4 + Doctrine ORM 3.x, 71 migraciones, PKs **autoincrementales** (secuencias nativas PG — no usa UUIDs).
- Deploy mediante `deploy.sh` con overlays `docker-compose.vps.{prod,staging}.yaml` sobre `docker-compose.vps.yaml`.
- RabbitMQ 3.13 con 5 colas async.

---

## Respuesta corta: ¿se puede?

**Sí.** Pero hay que entender qué soporta PostgreSQL nativamente:

| Modo | Soporte nativo PG18 | Estado |
|---|---|---|
| Replicación física (streaming) | Sí | Solo lectura en standby; no sirve para doble escritura |
| Replicación lógica unidireccional | Sí | Prod → Nuevo; ideal para migración |
| Replicación lógica cruzada (bidireccional) | Sí (con limitaciones) | Dos publicaciones cruzadas; requiere gestionar colisiones de PK y loops |
| Multi-master activo-activo nativo | **No** | Requiere pglogical/BDR (extensiones externas o comerciales) |

**Recomendación principal**: para una migración, la opción más segura y simple es **replicación unidireccional prod → nuevo + cutover controlado**. La bidireccional se cubre en este plan para el caso en que se necesite doble escritura real durante semanas.

**Nota**: Este plan asume `postgres:18-alpine` como imagen base. La opción `origin = 'none'` en las suscripciones lógicas (protección nativa contra loops en topología bidireccional) está disponible desde PG16 y funciona en PG18 sin cambios.

---

## Arquitectura objetivo

```
     ┌──────────────────────────┐   WireGuard    ┌──────────────────────────┐
     │  VPS-PROD  (Nodo A)      │    10.8.0.0/24 │  VPS-NUEVO (Nodo B)      │
     │                          │                │                          │
     │  postgres:18-alpine      │◀──────────────▶│  postgres:18-alpine      │
     │  wal_level = logical     │                │  wal_level = logical     │
     │                          │                │                          │
     │  PUBLICATION pub_a       │──── datos ────▶│  SUBSCRIPTION ← pub_a   │
     │  SUBSCRIPTION ← pub_b   │◀─── datos ─────│  PUBLICATION pub_b       │
     │                          │                │                          │
     │  Secuencias: IMPARES     │                │  Secuencias: PARES       │
     │  (START 1, INCREMENT 2)  │                │  (START 2, INCREMENT 2)  │
     └──────────────────────────┘                └──────────────────────────┘
```

**Reglas clave:**
- El transporte es **WireGuard punto-a-punto** entre los dos VPS. Nunca se expone el puerto 5432 al Internet público.
- Cada nodo publica todas sus tablas y se suscribe a la publicación del otro.
- **Anticolisión de PK**: Nodo A genera IDs impares, Nodo B genera IDs pares → matemáticamente imposible que colisionen.

---

## Componentes que hay que crear/modificar

### Archivos nuevos (solo infraestructura de replicación, no toca la app)

```
docker-compose.vps.replication.yaml          ← overlay de Docker con parámetros de Postgres para replicación
docker/postgres/pg_hba.conf                  ← reglas de acceso para el rol replicator desde la red WG
docker/postgres/replication/
    setup-node.sql                           ← script SQL parametrizado por nodo (crea rol, publication, subscription)
    partition-sequences.sql                  ← ajusta todas las secuencias a INCREMENT BY 2 (par o impar)
    check-replication.sh                     ← monitor de lag y estado de slots/subscripciones
docs/replication-plan.md                     ← este fichero
```

### Archivos a modificar

| Archivo | Cambio |
|---|---|
| `docker-compose.vps.yaml` | Ya actualizado a `postgres:18-alpine` (ver plan de upgrade PG15→18) |
| `deploy.sh` | Agregar acciones `--replication up\|down\|status`, `--backup --data-only`, `--migrate-replicated` |
| `.env.vps.prod.example` | Nuevas variables: `REPL_USER`, `REPL_PASSWORD`, `REPL_NODE_NAME`, `REPL_PEER_HOST`, `SEQUENCE_OFFSET`, `SEQUENCE_INCREMENT` |
| `.env.vps.staging.example` | Ídem |

**Lo que NO se toca:** `config/packages/doctrine.yaml`, las entidades, ni la `DATABASE_URL`. La app continúa apuntando a su Postgres local sin saber que hay replicación.

---

## Secuencia de operaciones

### Paso A — Preparar Nodo B (nuevo servidor)

```bash
git clone <repo> .
cp .env.vps.prod.example .env.vps
# Editar .env.vps: dominio, credenciales, REPL_NODE_NAME=node_b, REPL_PEER_HOST=10.8.0.1, SEQUENCE_OFFSET=2

./deploy.sh prod --setup
# Resultado: servicios levantados, 71 migraciones Doctrine ejecutadas
```

**Verificar paridad de schema:**
```bash
pg_dump --schema-only -h nodo-a ... > schema_a.sql
pg_dump --schema-only -h nodo-b ... > schema_b.sql
diff schema_a.sql schema_b.sql
# Debe ser idéntico (solo diferirán nombres de secuencias opcionalmente)
```

---

### Paso B — Snapshot inicial de datos (~5 minutos de downtime en prod)

```bash
# En Nodo A (prod quieto):
./deploy.sh prod --backup --data-only
# Genera backups/prod/db_<timestamp>.dump (pg_dump -Fc --data-only)

scp backups/prod/db_<ts>.dump usuario@vps-nuevo:/tmp/
```

```bash
# En Nodo B — vaciar tablas (conservar doctrine_migration_versions):
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

# Restaurar solo datos:
docker exec -i <container_db> pg_restore \
  --data-only --disable-triggers \
  -d $POSTGRES_DB < /tmp/db_<ts>.dump
```

---

### Paso C — Habilitar `wal_level=logical` (restart breve, ambos nodos)

```bash
# En cada nodo (uno a la vez):
./deploy.sh <env> --replication up
# Usa el overlay docker-compose.vps.replication.yaml que pasa los flags de Postgres

# Verificar:
docker exec <container_db> psql -U $POSTGRES_USER -c "SHOW wal_level;"
# Debe responder: logical
```

El overlay habilita estos parámetros vía `command:` en el servicio `database`:
```
wal_level=logical
max_wal_senders=10
max_replication_slots=10
max_logical_replication_workers=8
track_commit_timestamp=on
```

---

### Paso D — Crear rol, publicaciones y suscripciones

**En Nodo A (y replicar el bloque en B cambiando los nombres):**

```sql
-- 1. Rol de replicación (ejecutar en ambos nodos)
CREATE ROLE replicator WITH REPLICATION LOGIN PASSWORD 'CAMBIAR_ESTO';
GRANT CONNECT ON DATABASE api_transferencias TO replicator;
GRANT USAGE ON SCHEMA public TO replicator;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO replicator;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO replicator;

-- 2. Publicación en Nodo A
CREATE PUBLICATION pub_a FOR ALL TABLES;
```

```sql
-- 2. Publicación en Nodo B
CREATE PUBLICATION pub_b FOR ALL TABLES;
```

**Suscripción en Nodo B (recibe cambios de A):**
```sql
CREATE SUBSCRIPTION sub_b_from_a
  CONNECTION 'host=10.8.0.1 port=5432 dbname=api_transferencias user=replicator password=CAMBIAR_ESTO'
  PUBLICATION pub_a
  WITH (
    copy_data         = false,           -- los datos ya están del snapshot del paso B
    create_slot       = true,
    slot_name         = 'slot_a_to_b',
    synchronous_commit = 'off',
    origin            = 'none'           -- CRÍTICO: evita loops (solo PG16+)
  );
```

**Suscripción en Nodo A (recibe cambios de B):**
```sql
CREATE SUBSCRIPTION sub_a_from_b
  CONNECTION 'host=10.8.0.2 port=5432 dbname=api_transferencias user=replicator password=CAMBIAR_ESTO'
  PUBLICATION pub_b
  WITH (
    copy_data         = false,
    create_slot       = true,
    slot_name         = 'slot_b_to_a',
    synchronous_commit = 'off',
    origin            = 'none'
  );
```

---

### Paso E — Particionar secuencias (evitar colisiones de PK)

Las secuencias **no se replican** por replicación lógica. El valor del ID viaja dentro de la fila, pero la secuencia local del receptor no avanza. Sin esta partición, dos inserts simultáneos en ambos nodos pueden generar el mismo ID.

**En Nodo A — IDs IMPARES:**
```sql
DO $$ DECLARE s record; nv bigint;
BEGIN
  FOR s IN SELECT schemaname, sequencename, last_value
           FROM pg_sequences WHERE schemaname = 'public'
  LOOP
    nv := s.last_value + 1;
    IF (nv % 2) = 0 THEN nv := nv + 1; END IF;  -- forzar impar
    EXECUTE format(
      'ALTER SEQUENCE %I.%I INCREMENT BY 2 RESTART WITH %s',
      s.schemaname, s.sequencename, nv
    );
  END LOOP;
END $$;
```

**En Nodo B — IDs PARES:**
```sql
DO $$ DECLARE s record; nv bigint;
BEGIN
  FOR s IN SELECT schemaname, sequencename, last_value
           FROM pg_sequences WHERE schemaname = 'public'
  LOOP
    nv := s.last_value + 2;
    IF (nv % 2) = 1 THEN nv := nv + 1; END IF;  -- forzar par
    EXECUTE format(
      'ALTER SEQUENCE %I.%I INCREMENT BY 2 RESTART WITH %s',
      s.schemaname, s.sequencename, nv
    );
  END LOOP;
END $$;
```

**Regla mientras dure la replicación:** prohibido ejecutar `ALTER SEQUENCE` manualmente en ninguno de los dos nodos.

---

### Paso F — Arranque gradual en Nodo B

1. Apuntar el dominio del nuevo servidor a Nodo B (Traefik configurado).
2. **Smoke test bidireccional** (ver sección Verificación).
3. Migrar clientes progresivamente: 10% → 25% → 50% de tráfico vía DNS weighted o balanceador.
4. Monitorear lag diariamente (ver queries de monitoreo más abajo).

---

### Paso G — Cutover final (dispara el desmantelamiento)

```bash
# 1. Congelar escrituras en Nodo A
docker exec <container_db> psql -U $POSTGRES_USER \
  -c "ALTER DATABASE api_transferencias SET default_transaction_read_only = on;"
# O simplemente detener php-fpm + workers en el nodo A

# 2. Esperar lag 0 en Nodo A (Nodo B lo ha recibido todo)
docker exec <container_db> psql -U $POSTGRES_USER -c \
  "SELECT slot_name, confirmed_flush_lsn = pg_current_wal_lsn() AS caught_up
   FROM pg_replication_slots;"
# caught_up debe ser TRUE en ambos slots

# 3. Drenar colas RabbitMQ de Nodo A
docker exec <container_rabbitmq> rabbitmqctl list_queues
# Esperar hasta que todas las colas lleguen a 0 mensajes

# 4. Apuntar DNS principal a Nodo B
# A partir de aquí el 100% del tráfico va a Nodo B

# 5. Guardar snapshot de Nodo A como seguro de rollback
./deploy.sh prod --backup
# Conservar al menos 30 días antes de destruir el VPS

# 6. Detener Nodo A definitivamente
./deploy.sh prod --stop
```

Continuar inmediatamente con el Paso H.

---

### Paso H — Desmantelamiento del cluster (Nodo B queda solo)

Una vez que Nodo A está detenido y el 100% del tráfico va a Nodo B, se elimina toda la infraestructura de replicación. Al final del paso H el sistema queda exactamente igual que una instalación nueva: un solo Postgres, sin roles de replicación, sin slots, sin WireGuard, sin secuencias particionadas.

**H1 — Eliminar suscripciones y publicaciones en Nodo B**

```sql
-- Eliminar las suscripciones (también elimina sus replication slots en el origen)
DROP SUBSCRIPTION sub_b_from_a;   -- slot slot_a_to_b en Nodo A ya no existe (A está apagado)
                                   -- Postgres lanza warning si no puede contactar al origen; es esperado

-- Verificar que no quedan suscripciones activas
SELECT subname FROM pg_subscription;
-- Debe devolver 0 filas

-- Eliminar la publicación propia (ya no hay suscriptores)
DROP PUBLICATION pub_b;

-- Verificar
SELECT pubname FROM pg_publication;
-- Debe devolver 0 filas
```

**H2 — Limpiar slots huérfanos**

```sql
-- Listar todos los slots (deben estar todos inactivos)
SELECT slot_name, active, confirmed_flush_lsn FROM pg_replication_slots;

-- Eliminar cada slot inactivo que quede
SELECT pg_drop_replication_slot(slot_name)
FROM pg_replication_slots
WHERE active = false;

-- Confirmar que no quedan slots
SELECT count(*) FROM pg_replication_slots;
-- Debe ser 0
```

**H3 — Eliminar el rol `replicator`**

```sql
-- Revocar permisos antes de eliminar el rol
REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM replicator;
REVOKE ALL PRIVILEGES ON SCHEMA public FROM replicator;
REVOKE CONNECT ON DATABASE api_transferencias FROM replicator;
ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE SELECT ON TABLES FROM replicator;

-- Eliminar el rol
DROP ROLE replicator;

-- Verificar
SELECT rolname FROM pg_roles WHERE rolname = 'replicator';
-- Debe devolver 0 filas
```

**H4 — Restaurar secuencias a INCREMENT BY 1**

Las secuencias aún tienen `INCREMENT BY 2` del paso E. Hay que normalizarlas para que los IDs vuelvan a ser consecutivos a partir del valor más alto real de cada tabla.

> ⚠️ **Usar `MAX(id)` de la tabla, NO `pg_sequences.last_value`.**
> Durante la convivencia Nodo B solo emitió pares; los impares que llegaron por replicación lógica desde Nodo A se insertaron en la tabla pero no avanzaron `last_value` de Nodo B. Por tanto `last_value < MAX(id)`, y usar `last_value + 1` produciría una violación de PK en el primer INSERT post-cutover.
> Ejecutar `scripts/phase9-consolidate-sequences.py` para hacerlo de forma automatizada con pre/post-checks.

```sql
DO $$
DECLARE
  s record;
  tbl text;
  col text := 'id';
  max_val bigint;
  nv bigint;
BEGIN
  FOR s IN
    SELECT schemaname, sequencename
    FROM pg_sequences
    WHERE schemaname = 'public'
    ORDER BY sequencename
  LOOP
    tbl := regexp_replace(s.sequencename, '_id_seq$', '');

    IF NOT EXISTS (
      SELECT FROM information_schema.columns
      WHERE table_schema = s.schemaname
        AND table_name   = tbl
        AND column_name  = col
    ) THEN
      RAISE NOTICE 'Secuencia % sin tabla/col asociada — omitiendo', s.sequencename;
      CONTINUE;
    END IF;

    EXECUTE format('SELECT COALESCE(MAX(%I), 0) FROM %I.%I',
                   col, s.schemaname, tbl) INTO max_val;

    nv := max_val + 1;

    EXECUTE format(
      'ALTER SEQUENCE %I.%I INCREMENT BY 1 RESTART WITH %s',
      s.schemaname, s.sequencename, nv
    );
    RAISE NOTICE '% → max_id=%, restart=%', s.sequencename, max_val, nv;
  END LOOP;
END $$;

-- Verificar que todas las secuencias tienen increment_by = 1
SELECT sequencename, increment_by, last_value FROM pg_sequences WHERE schemaname = 'public';
-- Todas deben mostrar increment_by = 1

-- Muestreo de tablas críticas: MAX(id) debe ser menor que last_value post-restart
SELECT 'balance_operation'         AS tbl, MAX(id) AS max_id,
       (SELECT last_value FROM balance_operation_id_seq) AS seq_last;
SELECT 'communication_sale_history' AS tbl, MAX(id) AS max_id,
       (SELECT last_value FROM communication_sale_history_id_seq) AS seq_last;
SELECT 'client'                    AS tbl, MAX(id) AS max_id,
       (SELECT last_value FROM client_id_seq) AS seq_last;
```

**H5 — Bajar el overlay de replicación y restaurar Docker Compose normal**

```bash
# Reemplazar el stack con replicación por el stack normal (sin overlay de replicación)
./deploy.sh prod --replication down
./deploy.sh prod deploy
# Esto recrea el contenedor database SIN el overlay, sin puertos WG expuestos
# y sin los parámetros wal_level/max_wal_senders extra

# Verificar que el Postgres ya no escucha en la interfaz WG
docker exec <container_db> psql -U $POSTGRES_USER -c "SHOW listen_addresses;"
# Debe mostrar solo * o localhost (red interna Docker), NO la IP de WG
```

**H6 — Eliminar pg_hba.conf custom y volver al default**

```bash
# Borrar el fichero montado
rm docker/postgres/pg_hba.conf

# El siguiente deploy levantará el contenedor sin el montaje custom
# y Postgres usará su pg_hba.conf por defecto (solo red interna Docker)
./deploy.sh prod deploy
```

**H7 — Eliminar variables de replicación del .env.vps**

Eliminar estas líneas del `.env.vps` real en el servidor:
```
REPL_USER
REPL_PASSWORD
REPL_NODE_NAME
REPL_PEER_HOST
REPL_PEER_PORT
SEQUENCE_OFFSET
SEQUENCE_INCREMENT
```

**H8 — Desactivar WireGuard**

```bash
# En el VPS-NUEVO (Nodo B) — ahora el único VPS activo
sudo wg-quick down wg0
sudo systemctl disable wg-quick@wg0

# Verificar que la interfaz wg0 ya no existe
ip link show wg0
# Debe dar error "does not exist"
```

**H9 — Verificación final: sistema limpio**

```sql
-- 1. Sin publicaciones
SELECT count(*) FROM pg_publication;          -- 0

-- 2. Sin suscripciones
SELECT count(*) FROM pg_subscription;         -- 0

-- 3. Sin slots de replicación
SELECT count(*) FROM pg_replication_slots;    -- 0

-- 4. Sin rol replicator
SELECT count(*) FROM pg_roles WHERE rolname = 'replicator';  -- 0

-- 5. Secuencias con increment_by = 1
SELECT sequencename, increment_by FROM pg_sequences
WHERE schemaname = 'public' AND increment_by <> 1;
-- 0 filas (todas son 1)

-- 6. Integridad de datos: contar filas por tabla
SELECT schemaname, tablename, n_live_tup
FROM pg_stat_user_tables
WHERE schemaname = 'public'
ORDER BY n_live_tup DESC;
-- Comparar con el snapshot de referencia tomado antes del cutover
```

```bash
# 7. La app funciona normalmente (sin tráfico del nodo antiguo)
curl -s https://api.comremit.com/api/health
# HTTP 200
```

**Estado final esperado:**
```
VPS-NUEVO
  └── postgres:18-alpine
        ├── BD: api_transferencias (datos completos)
        ├── Rol: solo app_user (sin replicator)
        ├── Slots: ninguno
        ├── Publicaciones: ninguna
        ├── Suscripciones: ninguna
        ├── Secuencias: INCREMENT BY 1
        └── Puerto 5432: solo red interna Docker (red app)
```

---

## Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| **UPDATE-UPDATE sobre la misma fila** en ambos nodos simultáneamente | Pérdida de datos (last-write-wins por commit timestamp) | Minimizar ventana de doble escritura. Para tablas de configuración global, escribir solo en A hasta el cutover. |
| **INSERT con mismo PK** (colisión) | Error de integridad referencial | Resuelto por particionamiento par/impar del paso E. |
| **DDL no se replica** (migraciones Doctrine) | Schema divergente entre nodos | Regla dura: durante la ventana bidireccional, **cero migraciones directas**. Usar `./deploy.sh --migrate-replicated` que pausa suscripciones, migra en ambos y reactiva. |
| **Tablas sin PK** no son replicables por replicación lógica | Esas tablas quedan sin sincronizar | Auditar antes del paso C. Añadir PK o ejecutar `ALTER TABLE t REPLICA IDENTITY FULL;` |
| **RabbitMQ no se replica** | Mensajes en vuelo pueden procesarse dos veces o perderse en cutover | Drenar colas antes del paso G. Detener workers del nodo A antes de proceder. |
| **Loop circular** (cambio de A replica en B que replica de vuelta en A) | Amplificación infinita | Cubierto por `origin='none'` (disponible desde PG16, activo en PG18). Ambos nodos deben ser PG16+. |
| **Slot abandonado llena el disco** | Disco lleno → Postgres falla | Monitor diario: `pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)`. Alertar si supera 2GB. |
| **Backup actual en SQL plano** no admite `--data-only` directo | No se puede hacer restore limpio para el snapshot | **Resuelto**: `scripts/upgrade-postgres.sh` ya usa `pg_dump -Fc`. Para el snapshot inicial del paso B, usar `pg_dump -Fc --data-only`. |

---

## Verificación

### Smoke test bidireccional

Después del paso E, antes de redirigir tráfico real:

```sql
-- Insertar en Nodo A (debe generar ID impar):
INSERT INTO clients (name, created_at, updated_at) VALUES ('repl-test-A', now(), now()) RETURNING id;

-- Esperar 2 segundos y verificar en Nodo B:
SELECT id, name FROM clients WHERE name = 'repl-test-A';
-- id debe ser impar y la fila debe aparecer

-- Insertar en Nodo B (debe generar ID par):
INSERT INTO clients (name, created_at, updated_at) VALUES ('repl-test-B', now(), now()) RETURNING id;

-- Esperar 2 segundos y verificar en Nodo A:
SELECT id, name FROM clients WHERE name = 'repl-test-B';
-- id debe ser par y la fila debe aparecer
```

### Queries de monitoreo continuo

```sql
-- Estado de slots (lado publisher) — ejecutar en cada nodo
SELECT
  slot_name,
  active,
  pg_size_pretty(pg_wal_lsn_diff(pg_current_wal_lsn(), confirmed_flush_lsn)) AS lag_size,
  pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn) AS wal_retained_bytes
FROM pg_replication_slots;

-- Estado de suscripciones (lado subscriber) — ejecutar en cada nodo
SELECT
  subname,
  received_lsn,
  EXTRACT(EPOCH FROM (now() - latest_end_time))::int AS lag_seconds
FROM pg_stat_subscription;

-- Replicación activa en tiempo real (publisher)
SELECT application_name, client_addr, state, sent_lsn, replay_lsn
FROM pg_stat_replication;
```

### Test completo en staging antes del cutover real

1. Clonar snapshot de prod en el entorno de staging.
2. Ejecutar los pasos A → G completos.
3. Criterio de éxito: `SELECT count(*), tablename FROM pg_tables WHERE schemaname='public' GROUP BY tablename` devuelve los mismos valores en ambos nodos tras el cutover.

---

## Plan de contingencia: degradar a unidireccional

Si durante el paso F se detectan conflictos o inestabilidad, la degradación a unidireccional es trivial y reversible:

```sql
-- En Nodo A: eliminar la suscripción que recibe de B
DROP SUBSCRIPTION sub_a_from_b;
```

Adicionalmente, bloquear escrituras en Nodo B a nivel app (variable de entorno o config de php-fpm).

**Resultado:** Nodo B es standby casi-síncrono, Nodo A el único writer. El cutover final del paso G se ejecuta exactamente igual. Esta opción elimina todos los riesgos de conflicto y de loop al precio de no tener doble escritura activa.

---

## Resumen de decisiones técnicas

| Decisión | Justificación |
|---|---|
| Cluster **temporal** (no permanente) | El objetivo es una sola BD. El cluster existe solo durante la ventana de migración; el paso H lo elimina por completo. |
| Replicación lógica nativa (PG16+, ejecutada sobre PG18) | Sin extensiones externas, sin licencias, sin imagen Docker custom. Fácil de desinstalar: solo `DROP SUBSCRIPTION / PUBLICATION`. |
| WireGuard como transporte | Más simple y seguro que abrir puertos o usar SSH tunnels. Se desactiva con `wg-quick down` en el paso H8. |
| Particionamiento par/impar de secuencias | Única solución sin código en la app para evitar colisiones de PK autoincremental. Se revierte a `INCREMENT BY 1` en el paso H4. |
| `origin='none'` (disponible desde PG16, usado aquí sobre PG18) | Protección nativa contra loops. Requisito para la topología bidireccional. |
| Overlay `docker-compose.vps.replication.yaml` | Mantiene el compose base sin cambios; la replicación es un modo explícito. Basta con no usarlo tras el cutover (paso H5). |
| `--migrate-replicated` en deploy.sh | Permite ejecutar migraciones Doctrine durante la ventana de convivencia sin romper la replicación. |
| Paso H como checklist de desmantelamiento | Garantiza que el sistema queda limpio: sin slots, sin roles, sin WG, sin secuencias particionadas, sin variables de entorno innecesarias. |
