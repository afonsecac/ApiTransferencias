#!/usr/bin/env bash
set -euo pipefail

# ===========================================
# Recoge el disparo bajo demanda del sync prod->staging desde el dashboard.
# Corre EN EL HOST DE PROD, via cron cada 1-2 minutos (ver docs/deployment.md
# para el crontab exacto de deploy@ — no versionado en git, igual que el cron
# mensual de sync-prod-to-staging.sh).
# ===========================================
#
# El contenedor php-fpm no tiene Docker CLI ni la llave SSH del sync, asi
# que App\Service\StagingSyncService::trigger() solo escribe un archivo de
# bandera en var/staging-sync-trigger/request.json (bind mount de solo esa
# carpeta, ver docker-compose.vps.prod.yaml). Este watcher es el unico que
# lo lee y ejecuta el script real, ya con acceso completo al host.

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

TRIGGER_FILE="var/staging-sync-trigger/request.json"

[ -f "$TRIGGER_FILE" ] || exit 0

# Extraccion simple: el archivo lo genera StagingSyncService::trigger() con
# json_encode() de un objeto plano de un solo nivel, sin anidar — no hace
# falta un parser JSON completo (jq puede no estar instalado en el host).
TRIGGERED_BY=$(grep -o '"triggeredBy":"[^"]*"' "$TRIGGER_FILE" | cut -d'"' -f4)
TRIGGERED_BY="${TRIGGERED_BY:-desconocido}"

# Reclamo atomico: se borra ANTES de lanzar el sync (que tarda varios
# minutos) para que el propio cron no vuelva a recogerla en su siguiente
# vuelta mientras esta sigue corriendo.
rm -f "$TRIGGER_FILE"

exec ./scripts/sync-prod-to-staging.sh "--triggered-by=$TRIGGERED_BY"
