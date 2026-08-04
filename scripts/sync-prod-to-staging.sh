#!/usr/bin/env bash
set -euo pipefail

# ===========================================
# Sincroniza un backup de prod hacia staging.
# Corre EN EL HOST DE PROD, disparado por dos vias:
#   - cron mensual (ver crontab de deploy@).
#   - scripts/staging-sync-watcher.sh, que recoge el disparo bajo demanda
#     desde el dashboard (App\Service\StagingSyncService::trigger()).
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
# 4. Las credenciales de proveedor (sys_config bajo provider.%) llegan en el
#    dump cifradas con la SYS_CONFIG_ENCRYPTION_KEY de prod, que staging no
#    puede descifrar con la suya. Decision explicita (2026-08-04, no la de
#    borrarlas): se envia la clave de prod SOLO por stdin de esta misma
#    conexion SSH restringida, staging la usa una vez para descifrar y
#    re-cifrar con su propia clave, y la descarta — quedan funcionales en
#    staging por ahora. Pendiente un mecanismo mejor mas adelante.
#
# Reporta su propio estado (RUNNING/SUCCESS/FAILED) hacia la app en cada
# checkpoint via `bin/console app:staging-sync:report` — es lo unico que le
# permite al dashboard (que no tiene acceso al host) enterarse en vivo.

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

# Evita que el cron mensual y un disparo bajo demanda (u otro disparo
# manual) corran a la vez: ambos invocan este mismo script. Si el lock ya
# esta tomado, esta es una invocacion duplicada de verdad — la que sigue
# corriendo ya esta reportando su propio estado, asi que esta simplemente
# se retira sin tocar el reporte.
LOCK_FILE="/tmp/sync-prod-to-staging.lock"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "[sync-prod-to-staging] Ya hay una sincronizacion en curso, saliendo." >&2
    exit 1
fi

STAGING_HOST="${STAGING_SYNC_HOST:-69.62.70.58}"
STAGING_USER="${STAGING_SYNC_USER:-deploy}"
STAGING_KEY="${STAGING_SYNC_KEY:-$HOME/.ssh/prod_to_staging_sync_ed25519}"
SSH_OPTS=(-o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=30 -i "$STAGING_KEY")
COMPOSE=(docker compose -f docker-compose.vps.yaml -f docker-compose.vps.prod.yaml --env-file .env.vps)

TRIGGERED_BY="cron"
for arg in "$@"; do
    case "$arg" in
        --triggered-by=*) TRIGGERED_BY="${arg#*=}" ;;
    esac
done

log()  { echo "[sync-prod-to-staging] $1"; }
error(){ echo "[sync-prod-to-staging][ERROR] $1" >&2; exit 1; }

# No debe hacer abortar el script si el propio reporte falla (p.ej. la app
# esta caida) — la sincronizacion en si es lo que importa, el reporte es
# solo para que el dashboard se entere.
report() {
    local status="$1"
    local error_msg="${2:-}"
    local args=(app:staging-sync:report "$status" "--triggered-by=$TRIGGERED_BY")
    [ -n "$error_msg" ] && args+=("--error=$error_msg")
    "${COMPOSE[@]}" exec -T php-fpm php bin/console "${args[@]}" || true
}

trap 'report FAILED "Fallo en la linea $LINENO: $BASH_COMMAND"' ERR

report RUNNING

log "Generando backup de prod..."
./deploy.sh prod --backup

DUMP_FILE=$(ls -t backups/prod/db_*.sql.gz 2>/dev/null | head -1)
[ -z "$DUMP_FILE" ] && error "No se encontro ningun dump recien creado en backups/prod/"
log "Dump a sincronizar: $DUMP_FILE"

log "Transfiriendo a staging ($STAGING_HOST)..."
rsync -az -e "ssh ${SSH_OPTS[*]}" \
  "$DUMP_FILE" "$STAGING_USER@$STAGING_HOST:backups/incoming/$(basename "$DUMP_FILE")"

PROD_SYS_CONFIG_KEY=$(grep '^SYS_CONFIG_ENCRYPTION_KEY=' .env.vps | cut -d= -f2-)
[ -z "$PROD_SYS_CONFIG_KEY" ] && error "SYS_CONFIG_ENCRYPTION_KEY no esta definida en .env.vps"

log "Disparando restore + saneo de environment en staging..."
echo "$PROD_SYS_CONFIG_KEY" | ssh "${SSH_OPTS[@]}" "$STAGING_USER@$STAGING_HOST" RESTORE_AND_SANITIZE
unset PROD_SYS_CONFIG_KEY

log "Sincronizacion completa."
report SUCCESS
