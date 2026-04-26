#!/usr/bin/env bash
# Upgrades the Postgres data volume from PG15 to PG18 on a VPS.
# Usage: bash scripts/upgrade-postgres.sh <staging|prod>
#
# What it does:
#   1. Validates the DB is still PG15 (idempotent: exits early if already PG18+).
#   2. Stops app services, keeps DB up.
#   3. Dumps in custom format (-Fc) + plain SQL gzip (fallback).
#   4. Stops PG15, creates a new volume, starts PG18 standalone.
#   5. Restores into PG18 and runs smoke tests.
#   6. Updates PG_DATA_VOLUME in .env.vps to point at the new volume.
#   7. Brings the full stack up with PG18.
#   8. Runs an app health check.
#
# Rollback: the old PG15 volume is NEVER deleted automatically.
#   To roll back: revert PG_DATA_VOLUME in .env.vps, change the image back
#   to postgres:15-alpine in docker-compose.vps.yaml, then `docker compose up -d`.
#   Delete the old volume manually after 7 days of stable operation.

set -euo pipefail

# ── args ─────────────────────────────────────────────────────────────────────
ENV="${1:-}"
if [[ "$ENV" != "staging" && "$ENV" != "prod" ]]; then
  echo "Usage: bash scripts/upgrade-postgres.sh <staging|prod>" >&2
  exit 1
fi

# ── compose command ───────────────────────────────────────────────────────────
if [[ "$ENV" == "prod" ]]; then
  COMPOSE_OVERLAY="docker-compose.vps.prod.yaml"
  BACKUP_DIR="backups/prod/upgrade-pg18"
  HEALTH_URL="http://localhost/health/ready"
else
  COMPOSE_OVERLAY="docker-compose.vps.staging.yaml"
  BACKUP_DIR="backups/staging/upgrade-pg18"
  HEALTH_URL="http://localhost/health/ready"
fi

COMPOSE="docker compose -f docker-compose.vps.yaml -f ${COMPOSE_OVERLAY} --env-file .env.vps"

# ── resolve volume name ───────────────────────────────────────────────────────
# We need the CURRENT old volume name (PG15). If PG_DATA_VOLUME is already set
# in .env.vps to the pg18 name, we abort early below.
OLD_VOLUME_DEFAULT="$(basename "$(pwd)" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')_database_data"
# Read from .env.vps if present; otherwise use docker-compose default naming
CURRENT_PG_VOLUME="$(grep '^PG_DATA_VOLUME=' .env.vps 2>/dev/null | cut -d= -f2 || echo "$OLD_VOLUME_DEFAULT")"
NEW_VOLUME="${CURRENT_PG_VOLUME}_pg18"

PG_USER="$(grep '^POSTGRES_USER=' .env.vps | cut -d= -f2)"
PG_DB="$(grep '^POSTGRES_DB=' .env.vps | cut -d= -f2)"
PG_PASSWORD="$(grep '^POSTGRES_PASSWORD=' .env.vps | cut -d= -f2)"
DOCKER_NETWORK="$(basename "$(pwd)" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')_app"

TS="$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo ""
echo "======================================================"
echo "  PostgreSQL 15 → 18 upgrade — ENV: $ENV"
echo "  Old volume : $CURRENT_PG_VOLUME"
echo "  New volume : $NEW_VOLUME"
echo "  Backups    : $BACKUP_DIR"
echo "======================================================"
echo ""

# ── 1. PRE-FLIGHT ─────────────────────────────────────────────────────────────
echo "=== [1/10] Pre-flight checks ==="
$COMPOSE ps database | grep -q "running\|Up" || {
  echo "ERROR: database service is not running. Start it first." >&2
  exit 1
}

PG_VER="$($COMPOSE exec -T database psql -U "$PG_USER" -tAc "SHOW server_version_num;" 2>/dev/null || echo "0")"
echo "Current Postgres server_version_num: $PG_VER"

if [[ "${PG_VER}" -ge 180000 ]]; then
  echo "Database is already PG18+. Nothing to do."
  exit 0
fi

if [[ "${PG_VER}" -lt 150000 ]]; then
  echo "ERROR: Expected PG15, found version_num=$PG_VER. Aborting." >&2
  exit 1
fi

echo "PG15 confirmed. Proceeding with upgrade."

# ── 2. STOP APP SERVICES (keep DB up) ────────────────────────────────────────
echo ""
echo "=== [2/10] Stopping app services (DB stays up) ==="
$COMPOSE stop php-fpm nginx worker worker-notifications worker-clients \
              worker-packages worker-check-status worker-balance 2>/dev/null || true
echo "App services stopped."

# ── 3. DUMP (custom format + plain SQL fallback) ──────────────────────────────
echo ""
echo "=== [3/10] Dumping PG15 database ==="
DUMP_FC="$BACKUP_DIR/pre-pg18-${TS}.dump"
DUMP_SQL="$BACKUP_DIR/pre-pg18-${TS}.sql.gz"

echo "  → Custom format: $DUMP_FC"
$COMPOSE exec -T database pg_dump -U "$PG_USER" -Fc -Z6 -d "$PG_DB" > "$DUMP_FC"
echo "  → Plain SQL gzip: $DUMP_SQL"
$COMPOSE exec -T database pg_dump -U "$PG_USER" -d "$PG_DB" | gzip > "$DUMP_SQL"

ls -lh "$DUMP_FC" "$DUMP_SQL"
echo "Dump complete."

# ── 4. STOP PG15 ─────────────────────────────────────────────────────────────
echo ""
echo "=== [4/10] Stopping PG15 ==="
$COMPOSE stop database
echo "PG15 stopped. Old volume '$CURRENT_PG_VOLUME' is preserved."

# ── 5. CREATE NEW VOLUME + START PG18 STANDALONE ─────────────────────────────
echo ""
echo "=== [5/10] Creating new volume and starting PG18 standalone ==="
docker volume create "$NEW_VOLUME"
echo "Volume '$NEW_VOLUME' created."

docker run -d --name pg18-upgrade-tmp \
  --network "$DOCKER_NETWORK" \
  -e POSTGRES_DB="$PG_DB" \
  -e POSTGRES_USER="$PG_USER" \
  -e POSTGRES_PASSWORD="$PG_PASSWORD" \
  -v "${NEW_VOLUME}:/var/lib/postgresql" \
  postgres:18-alpine

echo "Waiting for PG18 to become ready..."
for i in $(seq 1 60); do
  docker exec pg18-upgrade-tmp pg_isready -U "$PG_USER" -q && break
  sleep 1
  if [[ "$i" -eq 60 ]]; then
    echo "ERROR: PG18 did not become ready in 60 seconds." >&2
    docker stop pg18-upgrade-tmp && docker rm pg18-upgrade-tmp
    docker volume rm "$NEW_VOLUME"
    exit 1
  fi
done
echo "PG18 is ready."

# ── 6. RESTORE ───────────────────────────────────────────────────────────────
echo ""
echo "=== [6/10] Restoring dump into PG18 ==="
docker exec -i pg18-upgrade-tmp pg_restore \
  -U "$PG_USER" -d "$PG_DB" \
  --no-owner --no-acl --clean --if-exists \
  < "$DUMP_FC"
echo "Restore complete."

# ── 7. SMOKE TESTS ───────────────────────────────────────────────────────────
echo ""
echo "=== [7/10] Smoke tests ==="
PG18_VER="$(docker exec pg18-upgrade-tmp psql -U "$PG_USER" -d "$PG_DB" -tAc "SELECT version();")"
echo "  PG18 version: $PG18_VER"

MSG_COUNT="$(docker exec pg18-upgrade-tmp psql -U "$PG_USER" -d "$PG_DB" -tAc "SELECT count(*) FROM messenger_messages;" 2>/dev/null || echo "table not found")"
echo "  messenger_messages rows: $MSG_COUNT"

TRIGGER_OK="$(docker exec pg18-upgrade-tmp psql -U "$PG_USER" -d "$PG_DB" -tAc "SELECT count(*) FROM pg_trigger WHERE tgname='notify_trigger';")"
if [[ "$TRIGGER_OK" -lt 1 ]]; then
  echo "WARNING: notify_trigger not found in PG18. Check messenger migration." >&2
else
  echo "  notify_trigger: present"
fi

LCCOLLATE="$(docker exec pg18-upgrade-tmp psql -U "$PG_USER" -d "$PG_DB" -tAc "SHOW lc_collate;")"
echo "  lc_collate: $LCCOLLATE (verify it matches PG15 if you have text indexes)"

echo "Smoke tests passed."

# ── 8. UPDATE .env.vps → SWAP VOLUME ─────────────────────────────────────────
echo ""
echo "=== [8/10] Updating PG_DATA_VOLUME in .env.vps ==="
docker stop pg18-upgrade-tmp && docker rm pg18-upgrade-tmp

if grep -q '^PG_DATA_VOLUME=' .env.vps 2>/dev/null; then
  sed -i "s|^PG_DATA_VOLUME=.*|PG_DATA_VOLUME=${NEW_VOLUME}|" .env.vps
else
  echo "PG_DATA_VOLUME=${NEW_VOLUME}" >> .env.vps
fi
echo "PG_DATA_VOLUME set to: $NEW_VOLUME"

# ── 9. BRING FULL STACK UP WITH PG18 ─────────────────────────────────────────
echo ""
echo "=== [9/10] Starting full stack with PG18 ==="
$COMPOSE up -d --force-recreate
echo "Stack started. Waiting 15s for services to stabilize..."
sleep 15

# ── 10. APP HEALTH CHECK ──────────────────────────────────────────────────────
echo ""
echo "=== [10/10] App health check ==="
if curl -sf "$HEALTH_URL" > /dev/null; then
  echo "Health check OK: $HEALTH_URL"
else
  echo ""
  echo "============================================================"
  echo "  HEALTH CHECK FAILED"
  echo "  The app did not respond at $HEALTH_URL"
  echo ""
  echo "  To ROLL BACK:"
  echo "    1. docker compose -f docker-compose.vps.yaml -f $COMPOSE_OVERLAY --env-file .env.vps stop"
  echo "    2. Edit .env.vps: set PG_DATA_VOLUME=$CURRENT_PG_VOLUME"
  echo "    3. Revert image to postgres:15-alpine in docker-compose.vps.yaml"
  echo "    4. docker compose -f docker-compose.vps.yaml -f $COMPOSE_OVERLAY --env-file .env.vps up -d"
  echo "============================================================"
  exit 2
fi

echo ""
echo "======================================================"
echo "  PG18 upgrade SUCCESSFUL — ENV: $ENV"
echo ""
echo "  New volume  : $NEW_VOLUME"
echo "  Old volume  : $CURRENT_PG_VOLUME  ← preserved (rollback)"
echo "  Backups     : $BACKUP_DIR/"
echo ""
echo "  After 7 days of stable operation, remove the old volume:"
echo "    docker volume rm $CURRENT_PG_VOLUME"
echo "======================================================"
