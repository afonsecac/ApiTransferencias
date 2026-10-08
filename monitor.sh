#!/usr/bin/env bash
# Monitoreo básico del VPS de producción. Lo ejecuta cron cada 5 minutos:
#   */5 * * * * /opt/api-transferencias/monitor.sh
#
# Comprueba que la API responde (200 con "status":"ok" por HTTPS, vía
# scripts/health-check.sh), que el disco no pasa del 85 % y la RAM del 90 %.
#
# Avisa por el correo de la propia app (comando app:monitor:alert): el host no
# tiene MTA (ni mail, mailx ni sendmail) y `deploy` no puede escribir en /var/log,
# así que la versión anterior no podía avisar de nada. Se ejecuta con
# `docker compose run --no-deps`, que levanta un contenedor propio: el aviso sale
# aunque php-fpm esté caído, que es justo cuando hace falta.
#
# - Un aviso por incidencia, repetido cada MONITOR_REPEAT_MINUTES (60) mientras
#   dure, y un aviso de RECUPERADO cuando vuelve a la normalidad.
# - Si el correo no sale, la incidencia no se da por avisada y se reintenta en el
#   siguiente ciclo.
# - Silencioso: todo va al log (~/logs/api-monitor.log); cron mandaría cualquier
#   salida a un buzón local que no existe.
#
# Escrito en POSIX sh (sin arrays ni [[ ]]) para poder probarlo en el contenedor
# de tests (busybox); en el servidor corre con bash.
#
# Variables opcionales: MONITOR_APP_DIR, MONITOR_STATE_DIR, MONITOR_LOG_FILE,
# MONITOR_MAILTO, MONITOR_REPEAT_MINUTES, MONITOR_DISK_MAX, MONITOR_MEM_MAX.

APP_DIR="${MONITOR_APP_DIR:-/opt/api-transferencias}"
STATE_DIR="${MONITOR_STATE_DIR:-$HOME/.monitor-state}"
LOG_FILE="${MONITOR_LOG_FILE:-$HOME/logs/api-monitor.log}"
MAILTO="${MONITOR_MAILTO:-info@comremit.com}"
REPEAT_MINUTES="${MONITOR_REPEAT_MINUTES:-60}"
DISK_MAX="${MONITOR_DISK_MAX:-85}"
MEM_MAX="${MONITOR_MEM_MAX:-90}"

mkdir -p "$STATE_DIR" "$(dirname "$LOG_FILE")"

# Un solo monitor a la vez (un health check lento no debe solaparse con el siguiente ciclo).
# El descriptor 9 (el bloqueo) se cierra en los procesos hijos (`9>&-`): si lo heredaran,
# un hijo que sobreviva al script (p. ej. el temporizador de `timeout`) mantendría el
# bloqueo y el siguiente ciclo saldría en silencio sin comprobar nada.
if command -v flock >/dev/null 2>&1; then
    exec 9>"$STATE_DIR/lock"
    flock -n 9 || exit 0
fi

log() {
    printf '[%s] %s\n' "$(date '+%F %T')" "$*" >> "$LOG_FILE"
}

# send_alert <asunto> <cuerpo>: 0 si el correo salió.
send_alert() {
    if command -v timeout >/dev/null 2>&1; then
        TIMEOUT="timeout 180"
    else
        TIMEOUT=""
    fi
    (
        cd "$APP_DIR" 2>/dev/null || true
        $TIMEOUT docker compose \
            -f "$APP_DIR/docker-compose.vps.yaml" -f "$APP_DIR/docker-compose.vps.prod.yaml" \
            --env-file "$APP_DIR/.env.vps" \
            run --rm -T --no-deps php-fpm php bin/console app:monitor:alert \
            --to "$MAILTO" "$1" "$2"
    ) >> "$LOG_FILE" 2>&1 9>&-
}

# evaluate <clave> <etiqueta> <problema|""> : gestiona alerta, repetición y recuperación.
evaluate() {
    key="$1"
    label="$2"
    problem="$3"
    state_file="$STATE_DIR/$key"
    now=$(date +%s)
    host=$(hostname 2>/dev/null || uname -n)

    if [ -n "$problem" ]; then
        log "PROBLEMA [$key] $problem"
        last=$(cat "$state_file" 2>/dev/null || echo 0)
        case "$last" in ''|*[!0-9]*) last=0 ;; esac
        if [ -f "$state_file" ] && [ $((now - last)) -lt $((REPEAT_MINUTES * 60)) ]; then
            return 0   # ya avisado hace poco
        fi
        subject="[PROD] ALERTA $label $DOMAIN"
        body="$problem

Servidor: $host
Hora: $(date '+%F %T %Z')
Se repite cada $REPEAT_MINUTES minutos mientras siga así; se avisará al recuperarse."
        if send_alert "$subject" "$body"; then
            echo "$now" > "$state_file"
            log "ALERTA enviada [$key] a $MAILTO"
        else
            log "no se pudo enviar la alerta [$key]; se reintentará en el siguiente ciclo"
        fi
    elif [ -f "$state_file" ]; then
        subject="[PROD] RECUPERADO $label $DOMAIN"
        body="El problema de $label se ha resuelto.

Servidor: $host
Hora: $(date '+%F %T %Z')"
        if send_alert "$subject" "$body"; then
            rm -f "$state_file"
            log "RECUPERADO [$key]; aviso enviado a $MAILTO"
        else
            log "no se pudo enviar el aviso de recuperación [$key]; se reintentará en el siguiente ciclo"
        fi
    fi
}

DOMAIN=$(grep '^DOMAIN=' "$APP_DIR/.env.vps" 2>/dev/null | cut -d= -f2)

# --- API ---
api_problem=""
if [ -z "$DOMAIN" ]; then
    DOMAIN="(sin dominio)"
    api_problem="DOMAIN no definido en $APP_DIR/.env.vps: no se puede comprobar la API."
else
    health_out=$(HEALTH_RETRIES=2 HEALTH_RETRY_DELAY=5 sh "$APP_DIR/scripts/health-check.sh" \
        "https://$DOMAIN/health/ready" "$DOMAIN:443:127.0.0.1" 2>&1 9>&-) \
        || api_problem="La API no responde correctamente en https://$DOMAIN/health/ready.

$health_out"
fi
evaluate api API "$api_problem"

# --- Disco ---
disk=$(df / | tail -1 | awk '{print $5}' | tr -d '%')
disk_problem=""
case "$disk" in ''|*[!0-9]*) ;; *)
    [ "$disk" -gt "$DISK_MAX" ] && disk_problem="Disco al ${disk}% (umbral ${DISK_MAX}%)."
esac
evaluate disk DISCO "$disk_problem"

# --- RAM ---
mem=$(free | awk '/Mem/{printf("%.0f", $3/$2*100)}')
mem_problem=""
case "$mem" in ''|*[!0-9]*) ;; *)
    [ "$mem" -gt "$MEM_MAX" ] && mem_problem="RAM al ${mem}% (umbral ${MEM_MAX}%)."
esac
evaluate mem RAM "$mem_problem"

exit 0
