#!/usr/bin/env bash
set -euo pipefail

# ===========================================
# Sincroniza un backup de prod hacia staging, una vez al mes.
# Corre EN EL HOST DE PROD (via cron, ver crontab de deploy@).
# ===========================================
#
# 1. Genera un backup fresco de la BD de prod (reutiliza deploy.sh --backup).
# 2. Lo transfiere a staging por rsync, usando una clave SSH dedicada
#    (prod-to-staging-sync) restringida en staging a un forced command
#    (scripts/staging-sync-dispatch.sh): solo puede recibir el rsync o
#    disparar RESTORE_AND_SANITIZE, nunca ejecutar un comando arbitrario.
# 3. RESTORE_AND_SANITIZE en staging restaura el dump y neutraliza los
#    environment que eran PROD (type, client_secret, client_id, base_path):
#    sin esto, staging con datos reales podria terminar llamando a las APIs
#    reales de pago/comunicaciones con las credenciales reales.

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

STAGING_HOST="${STAGING_SYNC_HOST:-69.62.70.58}"
STAGING_USER="${STAGING_SYNC_USER:-deploy}"
STAGING_KEY="${STAGING_SYNC_KEY:-$HOME/.ssh/prod_to_staging_sync_ed25519}"
SSH_OPTS=(-o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=30 -i "$STAGING_KEY")

log()  { echo "[sync-prod-to-staging] $1"; }
error(){ echo "[sync-prod-to-staging][ERROR] $1" >&2; exit 1; }

log "Generando backup de prod..."
./deploy.sh prod --backup

DUMP_FILE=$(ls -t backups/prod/db_*.sql.gz 2>/dev/null | head -1)
[ -z "$DUMP_FILE" ] && error "No se encontro ningun dump recien creado en backups/prod/"
log "Dump a sincronizar: $DUMP_FILE"

log "Transfiriendo a staging ($STAGING_HOST)..."
rsync -az -e "ssh ${SSH_OPTS[*]}" \
  "$DUMP_FILE" "$STAGING_USER@$STAGING_HOST:backups/incoming/$(basename "$DUMP_FILE")"

log "Disparando restore + saneo de environment en staging..."
ssh "${SSH_OPTS[@]}" "$STAGING_USER@$STAGING_HOST" RESTORE_AND_SANITIZE

log "Sincronizacion completa."
