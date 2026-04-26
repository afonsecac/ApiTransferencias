#!/usr/bin/env bash
set -euo pipefail

# ===========================================
# Script de despliegue para VPS (staging / prod)
# ===========================================
# Uso:
#   ./deploy.sh staging --setup     Primera instalacion en VPS staging
#   ./deploy.sh prod --setup        Primera instalacion en VPS produccion
#   ./deploy.sh staging             Despliegue estandar
#   ./deploy.sh prod --migrate      Despliegue + migraciones
#   ./deploy.sh prod --backup       Backup de la DB
# ===========================================

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()   { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }

# ===========================================
# Validar entorno
# ===========================================
ENV="${1:-}"
ACTION="${2:-deploy}"

if [[ "$ENV" != "staging" && "$ENV" != "prod" ]]; then
    echo ""
    echo "Uso: $0 <entorno> [accion]"
    echo ""
    echo "Entornos:"
    echo "  staging   VPS de staging (branch: develop)"
    echo "  prod      VPS de produccion (branch: master)"
    echo ""
    echo "Acciones:"
    echo "  (sin args)     Despliegue estandar (git pull + build + up)"
    echo "  --setup        Primera instalacion en el VPS"
    echo "  --build-only   Solo construir imagenes"
    echo "  --migrate      Despliegue + migraciones de DB"
    echo "  --logs [svc]   Ver logs (opcionalmente de un servicio)"
    echo "  --status       Estado de los contenedores"
    echo "  --backup       Backup de la base de datos"
    echo "  --restore <f>  Restaurar backup de DB"
    echo "  --stop         Detener todos los servicios"
    echo "  --restart      Reiniciar todos los servicios"
    echo "  --shell        Shell interactivo en php-fpm"
    echo "  --console <c>  Ejecutar bin/console"
    echo "  --rollback     Revertir al commit anterior y redesplegar"
    echo ""
    echo "Ejemplos:"
    echo "  $0 staging --setup"
    echo "  $0 prod --migrate"
    echo "  $0 staging --logs worker"
    echo "  $0 prod --backup"
    echo "  $0 prod --console 'doctrine:migrations:status'"
    echo ""
    exit 1
fi

# Branch por entorno
if [[ "$ENV" == "staging" ]]; then
    GIT_BRANCH="develop"
else
    GIT_BRANCH="master"
fi

COMPOSE_BASE="docker-compose.vps.yaml"
COMPOSE_ENV="docker-compose.vps.${ENV}.yaml"
ENV_FILE=".env.vps"

COMPOSE_CMD="docker compose -f $COMPOSE_BASE -f $COMPOSE_ENV --env-file $ENV_FILE"

info "Entorno: ${CYAN}${ENV}${NC} | Branch: ${CYAN}${GIT_BRANCH}${NC}"
info "Compose: $COMPOSE_BASE + $COMPOSE_ENV"

# ===========================================
# Verificaciones
# ===========================================
check_env() {
    if [ ! -f "$ENV_FILE" ]; then
        error "No se encontro $ENV_FILE
  Para $ENV, copia el template:
    cp .env.vps.${ENV}.example .env.vps
  Luego edita los valores:
    nano .env.vps"
    fi
}

check_docker() {
    if ! command -v docker &> /dev/null; then
        error "Docker no esta instalado. Instalar con: curl -fsSL https://get.docker.com | sh"
    fi
    if ! docker compose version &> /dev/null; then
        error "Docker Compose no esta disponible."
    fi
}

get_env_val() {
    grep "^${1}=" "$ENV_FILE" | head -1 | cut -d= -f2
}

# ===========================================
# Primera instalacion
# ===========================================
setup() {
    log "Configuracion inicial del VPS ($ENV)..."
    check_docker
    check_env

    log "Cambiando a branch $GIT_BRANCH..."
    git fetch origin "$GIT_BRANCH"
    git checkout "$GIT_BRANCH"
    git pull origin "$GIT_BRANCH"

    log "Construyendo imagenes (esto puede tardar ~5 min)..."
    $COMPOSE_CMD build

    log "Levantando servicios..."
    $COMPOSE_CMD up -d

    log "Esperando a que la base de datos este lista..."
    for i in $(seq 1 30); do
        if $COMPOSE_CMD exec -T database pg_isready -U "$(get_env_val POSTGRES_USER)" > /dev/null 2>&1; then
            break
        fi
        sleep 1
    done

    log "Ejecutando migraciones..."
    $COMPOSE_CMD exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction

    log "Limpiando cache..."
    $COMPOSE_CMD exec php-fpm php bin/console cache:clear

    log "Verificando health check..."
    sleep 5
    if $COMPOSE_CMD exec -T php-fpm php bin/console debug:router | grep -q health_live; then
        log "Rutas health registradas correctamente"
    else
        warn "Las rutas de health check no se encontraron"
    fi

    local DOMAIN_VAL
    DOMAIN_VAL=$(get_env_val "DOMAIN")

    echo ""
    log "========================================="
    log "  Instalacion completada: $ENV"
    log "========================================="
    log "API:              https://${DOMAIN_VAL}"
    log "API Docs:         https://${DOMAIN_VAL}/api/docs"
    log "Health:           https://${DOMAIN_VAL}/health/ready"
    log "Traefik:          https://traefik.${DOMAIN_VAL}"
    log "RabbitMQ:         https://rabbitmq.${DOMAIN_VAL}"
    if [[ "$ENV" == "staging" ]]; then
        log "Mailcatcher:      https://mail.${DOMAIN_VAL}"
    fi
    log "========================================="
}

# ===========================================
# Build
# ===========================================
build() {
    log "Construyendo imagenes ($ENV)..."
    $COMPOSE_CMD build
}

# ===========================================
# Despliegue estandar
# ===========================================
deploy() {
    check_env

    log "Obteniendo ultimos cambios de '$GIT_BRANCH'..."
    git fetch origin "$GIT_BRANCH"
    git checkout "$GIT_BRANCH"
    git pull origin "$GIT_BRANCH"

    log "Construyendo imagenes..."
    $COMPOSE_CMD build

    if [[ "$ENV" == "prod" ]]; then
        log "Creando backup automatico antes del despliegue..."
        backup_db
    fi

    log "Desplegando..."
    $COMPOSE_CMD up -d --remove-orphans --force-recreate

    log "Limpiando cache de Symfony..."
    $COMPOSE_CMD exec php-fpm php bin/console cache:clear

    log "Limpiando imagenes Docker sin usar..."
    docker image prune -f

    log "=== Despliegue $ENV completado ==="
}

# ===========================================
# Despliegue con migraciones
# ===========================================
deploy_with_migrate() {
    check_env

    log "Obteniendo ultimos cambios de '$GIT_BRANCH'..."
    git fetch origin "$GIT_BRANCH"
    git checkout "$GIT_BRANCH"
    git pull origin "$GIT_BRANCH"

    log "Construyendo imagenes..."
    $COMPOSE_CMD build

    if [[ "$ENV" == "prod" ]]; then
        log "Creando backup automatico antes de las migraciones..."
        backup_db
    fi

    log "Ejecutando migraciones..."
    $COMPOSE_CMD up -d database rabbitmq
    log "Esperando a que la base de datos este lista..."
    for i in $(seq 1 30); do
        if $COMPOSE_CMD exec -T database pg_isready -U "$(get_env_val POSTGRES_USER)" > /dev/null 2>&1; then
            break
        fi
        sleep 1
    done
    $COMPOSE_CMD run --rm php-fpm php bin/console doctrine:migrations:migrate --no-interaction

    log "Desplegando aplicacion..."
    $COMPOSE_CMD up -d --remove-orphans --force-recreate

    log "Limpiando cache..."
    $COMPOSE_CMD exec php-fpm php bin/console cache:clear

    docker image prune -f

    log "=== Despliegue $ENV con migraciones completado ==="
}

# ===========================================
# Logs
# ===========================================
show_logs() {
    $COMPOSE_CMD logs -f "${1:-}"
}

# ===========================================
# Estado
# ===========================================
status() {
    $COMPOSE_CMD ps
}

# ===========================================
# Backup DB
# ===========================================
backup_db() {
    local BACKUP_DIR="$PROJECT_DIR/backups/${ENV}"
    local TIMESTAMP
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    mkdir -p "$BACKUP_DIR"

    local PG_USER PG_DB
    PG_USER=$(get_env_val "POSTGRES_USER")
    PG_DB=$(get_env_val "POSTGRES_DB")

    log "Creando backup de la base de datos ($ENV)..."
    $COMPOSE_CMD exec -T database \
        pg_dump -U "$PG_USER" "$PG_DB" \
        | gzip > "$BACKUP_DIR/db_${TIMESTAMP}.sql.gz"

    log "Backup: $BACKUP_DIR/db_${TIMESTAMP}.sql.gz"

    local KEEP=7
    [[ "$ENV" == "prod" ]] && KEEP=30
    ls -t "$BACKUP_DIR"/db_*.sql.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm
}

# ===========================================
# Restaurar backup
# ===========================================
restore_db() {
    local BACKUP_FILE="${1:-}"
    if [ -z "$BACKUP_FILE" ]; then
        local BACKUP_DIR="$PROJECT_DIR/backups/${ENV}"
        if [ -d "$BACKUP_DIR" ]; then
            echo "Backups disponibles para $ENV:"
            ls -lh "$BACKUP_DIR"/db_*.sql.gz 2>/dev/null || echo "  (ninguno)"
        fi
        error "Uso: $0 $ENV --restore <archivo.sql.gz>"
    fi
    [ ! -f "$BACKUP_FILE" ] && error "Archivo no encontrado: $BACKUP_FILE"

    local PG_USER PG_DB
    PG_USER=$(get_env_val "POSTGRES_USER")
    PG_DB=$(get_env_val "POSTGRES_DB")

    warn "Esto reemplazara TODA la base de datos de $ENV."
    warn "Base de datos: $PG_DB | Archivo: $BACKUP_FILE"
    warn "Ctrl+C en 5 segundos para cancelar..."
    sleep 5

    log "Restaurando backup..."
    gunzip -c "$BACKUP_FILE" | $COMPOSE_CMD exec -T database psql -U "$PG_USER" "$PG_DB"

    log "Backup restaurado en $ENV."
}

# ===========================================
# Rollback
# ===========================================
rollback() {
    check_env
    warn "Revirtiendo al commit anterior en $GIT_BRANCH..."

    local CURRENT_COMMIT
    CURRENT_COMMIT=$(git rev-parse --short HEAD)

    git checkout HEAD~1

    log "Commit actual: $CURRENT_COMMIT → $(git rev-parse --short HEAD)"
    log "Reconstruyendo imagenes..."
    $COMPOSE_CMD build

    log "Redesplegando..."
    $COMPOSE_CMD up -d --remove-orphans

    log "Limpiando cache..."
    $COMPOSE_CMD exec php-fpm php bin/console cache:clear

    log "=== Rollback en $ENV completado ==="
    warn "El repositorio esta en detached HEAD. Para volver: git checkout $GIT_BRANCH"
}

# ===========================================
# Stop / Restart / Shell / Console
# ===========================================
stop_services()  { log "Deteniendo ($ENV)...";  $COMPOSE_CMD down; log "Detenido."; }
restart_services(){ log "Reiniciando ($ENV)..."; $COMPOSE_CMD restart; log "Reiniciado."; }
open_shell()     { $COMPOSE_CMD exec php-fpm sh; }

run_console() {
    local CMD="${1:-}"
    [ -z "$CMD" ] && error "Uso: $0 $ENV --console '<comando>'"
    log "bin/console $CMD ($ENV)"
    $COMPOSE_CMD exec php-fpm php bin/console $CMD
}

# ===========================================
# Router
# ===========================================
case "$ACTION" in
    --setup)      setup ;;
    --build-only) check_env; build ;;
    --migrate)    deploy_with_migrate ;;
    --logs)       show_logs "${3:-}" ;;
    --status)     check_env; status ;;
    --backup)     check_env; backup_db ;;
    --restore)    check_env; restore_db "${3:-}" ;;
    --stop)       check_env; stop_services ;;
    --restart)    check_env; restart_services ;;
    --shell)      check_env; open_shell ;;
    --console)    check_env; run_console "${3:-}" ;;
    --rollback)   rollback ;;
    deploy)       deploy ;;
    *)            error "Accion desconocida: $ACTION. Usa '$0 --help'." ;;
esac
