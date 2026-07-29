#!/usr/bin/env bash
set -euo pipefail

# Forced command para la clave SSH "prod-to-staging-sync" (ver authorized_keys
# de deploy@staging): esa clave vive en el host de prod para el sync mensual y
# NUNCA debe poder ejecutar un comando arbitrario. Solo permite dos cosas:
#   1. Recibir el dump vía rsync hacia backups/incoming/
#   2. Disparar RESTORE_AND_SANITIZE, que restaura el dump más reciente y
#      neutraliza los environment que eran PROD (type, client_secret,
#      client_id, base_path) para que staging no pueda golpear APIs reales.

cd /opt/api-transferencias

case "${SSH_ORIGINAL_COMMAND:-}" in
    rsync\ --server*)
        exec $SSH_ORIGINAL_COMMAND
        ;;
    RESTORE_AND_SANITIZE)
        # || true: con set -e + pipefail, el glob sin coincidencias hace que
        # "ls" falle y aborte el script ANTES de llegar al mensaje de abajo.
        LATEST=$(ls -t backups/incoming/*.sql.gz 2>/dev/null | head -1) || true
        if [ -z "$LATEST" ]; then
            echo "No hay ningun dump en backups/incoming/" >&2
            exit 1
        fi

        ./deploy.sh staging --restore "$LATEST"

        PG_USER=$(grep ^POSTGRES_USER= .env.vps | cut -d= -f2)
        PG_DB=$(grep ^POSTGRES_DB= .env.vps | cut -d= -f2)

        docker compose -f docker-compose.vps.yaml -f docker-compose.vps.staging.yaml \
            --env-file .env.vps exec -T database psql -U "$PG_USER" -d "$PG_DB" -c "
                UPDATE environment
                SET type = 'TEST',
                    client_secret = 'DISABLED_BY_STAGING_SYNC',
                    client_id = 'DISABLED_BY_STAGING_SYNC',
                    base_path = 'https://staging-sync-disabled.invalid'
                WHERE type = 'PROD';
            "

        rm -f "$LATEST"
        echo "RESTORE_AND_SANITIZE_OK"
        ;;
    *)
        echo "Comando no permitido para esta clave (solo rsync o RESTORE_AND_SANITIZE)." >&2
        exit 1
        ;;
esac
