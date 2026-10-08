#!/bin/sh
# Comprobación de salud tras un despliegue.
#
# Uso: scripts/health-check.sh <url> [<host:puerto:ip>]
#
# Sale con 0 solo si la URL responde HTTP 200 *y* el cuerpo contiene
# "status":"ok". Cualquier otra cosa (redirección, 4xx, 5xx, cuerpo vacío o
# distinto, sin conexión) sale con 1.
#
# Por qué existe: `curl -sf http://localhost/health/ready` daba por bueno un
# 301. En producción Traefik redirige todo el HTTP a HTTPS, y `-f` solo falla
# con códigos >= 400, así que la comprobación pasaba mientras Traefik
# respondiera, estuviera la app viva o no. Por eso aquí no se siguen
# redirecciones: un 3xx es un fallo.
#
# El segundo argumento es el valor de `curl --resolve`, para llamar a Traefik
# del propio VPS (127.0.0.1) con el dominio real: valida certificado y routing
# sin depender de DNS ni del exterior.
#
# Reintenta mientras la app arranca (php-fpm tarda unos segundos tras recrear
# los contenedores). Ajustable con HEALTH_RETRIES (6) y HEALTH_RETRY_DELAY (5 s).

URL="${1:-}"
RESOLVE="${2:-}"

if [ -z "$URL" ]; then
    echo "Uso: $0 <url> [host:puerto:ip]" >&2
    exit 2
fi

RETRIES="${HEALTH_RETRIES:-6}"
DELAY="${HEALTH_RETRY_DELAY:-5}"
BODY_FILE=$(mktemp)
trap 'rm -f "$BODY_FILE"' EXIT

set -- -s --max-time 20 --retry "$RETRIES" --retry-delay "$DELAY" --retry-all-errors \
    -o "$BODY_FILE" -w '%{http_code}'
if [ -n "$RESOLVE" ]; then
    set -- "$@" --resolve "$RESOLVE"
fi

CODE=$(curl "$@" "$URL") || CODE=000
BODY=$(head -c 300 "$BODY_FILE")

if [ "$CODE" != "200" ]; then
    echo "Health check FALLÓ: $URL respondió HTTP $CODE" >&2
    case "$CODE" in
        3??) echo "Es una redirección (¿http en vez de https?); no se siguen redirecciones." >&2 ;;
    esac
    [ -n "$BODY" ] && echo "Respuesta: $BODY" >&2
    exit 1
fi

case "$BODY" in
    *'"status":"ok"'*)
        echo "Health check OK: $URL → $BODY"
        exit 0
        ;;
esac

echo "Health check FALLÓ: $URL respondió HTTP 200 pero la respuesta no indica estado ok: $BODY" >&2
exit 1
