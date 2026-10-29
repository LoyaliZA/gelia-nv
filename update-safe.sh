#!/bin/bash
# ==============================================================================
# Actualización de GeliaNV sin borrar el build ni la caché de rutas.
#
# Uso:
#   ./update-safe.sh                 # Actualización estándar
#   ./update-safe.sh --skip-git      # Sin git pull
#   ./update-safe.sh --yes           # No pregunta si hay que recrear contenedores
#   ./update-safe.sh --purge-assets  # Borra chunks viejos (si no, solo simula)
#   ./update-safe.sh --dry-run       # La purga solo informa, aunque vaya --purge-assets
#   ./update-safe.sh --quick         # Mismo camino. No omite respaldo ni verificación
#   ./update-safe.sh --rebuild       # Recompila la imagen. Corta el servicio
#   ./update-safe.sh --recreate-env  # Fuerza recrear app, queue y scheduler
#
# La copia anterior de este script está en update-safe.legacy.sh y no se invoca.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# shellcheck source=scripts/backup-db.sh
source "${SCRIPT_DIR}/scripts/backup-db.sh"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yaml}"
COMPOSE=()
COMPOSE_BIN_DISPLAY=""

APP_CONTAINER="${DOCKER_APP_CONTAINER:-gelianv_app}"
WEB_CONTAINER="${DOCKER_WEB_CONTAINER:-gelianv_web}"
DB_CONTAINER="${DOCKER_DB_CONTAINER:-gelianv_db}"
QUEUE_CONTAINER="${DOCKER_QUEUE_CONTAINER:-gelianv_queue}"
REVERB_CONTAINER="${DOCKER_REVERB_CONTAINER:-gelianv_reverb}"
# Vacío: se usa la rama del checkout. GIT_BRANCH solo si coincide con esa rama.

SKIP_GIT=false
REBUILD_IMAGE=false
FORCE_RECREATE_ENV=false
ASSUME_YES=false
PURGE_APPLY=false
DRY_RUN_FLAG=false
PREV_SHA=""

# ------------------------------------------------------------------------------
# Utilidades
# ------------------------------------------------------------------------------

log()  { printf '\n[%s] %s\n' "$(date '+%H:%M:%S')" "$*"; }
ok()   { printf '[%s] ✓ %s\n' "$(date '+%H:%M:%S')" "$*"; }
warn() { printf '[%s] ⚠ %s\n' "$(date '+%H:%M:%S')" "$*" >&2; }
fail() { printf '[%s] ✗ %s\n' "$(date '+%H:%M:%S')" "$*" >&2; exit 1; }

on_error() {
    local exit_code=$?
    fail "Actualización interrumpida en la línea ${BASH_LINENO[0]}. Código: ${exit_code}. Revise los logs arriba."
}
trap on_error ERR

usage() {
    sed -n '4,16p' "$0" | sed 's/^# \{0,1\}//'
    exit 0
}

parse_args() {
    for arg in "$@"; do
        case "$arg" in
            --skip-git)       SKIP_GIT=true ;;
            --rebuild)        REBUILD_IMAGE=true ;;
            --recreate-env)   FORCE_RECREATE_ENV=true ;;
            --yes)            ASSUME_YES=true ;;
            --purge-assets)   PURGE_APPLY=true ;;
            --dry-run)        DRY_RUN_FLAG=true ;;
            --quick)          ;;
            -h|--help)        usage ;;
            *)
                fail "Opción desconocida: ${arg}. Use --help."
                ;;
        esac
    done
    if $DRY_RUN_FLAG; then
        PURGE_APPLY=false
    fi
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Comando requerido no encontrado: $1"
}

detect_docker_compose() {
    if docker compose version >/dev/null 2>&1; then
        COMPOSE=(docker compose -f "$COMPOSE_FILE")
        COMPOSE_BIN_DISPLAY="docker compose"
    elif command -v docker-compose >/dev/null 2>&1 && docker-compose version >/dev/null 2>&1; then
        COMPOSE=(docker-compose -f "$COMPOSE_FILE")
        COMPOSE_BIN_DISPLAY="docker-compose"
    else
        fail "No se encontró Docker Compose. Requiere 'docker compose' (plugin v2) o 'docker-compose'."
    fi

    ok "Docker Compose detectado: ${COMPOSE_BIN_DISPLAY} (${COMPOSE_FILE})"
}

container_running() {
    docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null | grep -q true
}

exec_app() {
    docker exec -i -w /var/www/html "$APP_CONTAINER" "$@"
}

env_file_value() {
    local key="$1"
    grep -E "^${key}=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' || true
}

container_env_value() {
    local container="$1"
    local key="$2"
    docker exec -i "$container" printenv "$key" 2>/dev/null || true
}

claves_distintas() {
    local container="$1"
    shift
    local key file_val container_val
    for key in "$@"; do
        file_val="$(env_file_value "$key")"
        container_val="$(container_env_value "$container" "$key")"
        if [ -n "$file_val" ] && [ "$file_val" != "$container_val" ]; then
            warn "Desincronización detectada: ${key} (.env=${file_val}, contenedor=${container_val:-<vacío>})"
            return 0
        fi
    done
    return 1
}

env_needs_recreate() {
    if $FORCE_RECREATE_ENV; then
        return 0
    fi

    if [ ! -f .env ]; then
        return 1
    fi

    claves_distintas "$APP_CONTAINER" SESSION_DRIVER APP_ENV APP_URL CACHE_STORE QUEUE_CONNECTION
}

reverb_needs_recreate() {
    if [ ! -f .env ]; then
        return 1
    fi

    local key
    while IFS= read -r key; do
        [ -n "$key" ] || continue
        if claves_distintas "$REVERB_CONTAINER" "$key"; then
            return 0
        fi
    done < <(grep -E '^REVERB_[A-Za-z0-9_]+=' .env 2>/dev/null | cut -d= -f1 || true)

    return 1
}

confirmar_recreacion() {
    if $ASSUME_YES; then
        return 0
    fi
    if [ ! -r /dev/tty ]; then
        fail "Hay que recrear contenedores y no hay terminal. Repite el comando con --yes."
    fi
    local respuesta
    read -r -p "Este despliegue recreará contenedores (corte breve). ¿Continuar? (s/n) " respuesta </dev/tty
    case "$respuesta" in
        s|S) return 0 ;;
        *) fail "Recreación cancelada." ;;
    esac
}

recargar_nginx() {
    log "Recargando Nginx sin cortar conexiones..."
    docker exec -i "$WEB_CONTAINER" nginx -s reload
    ok "Nginx recargado."
}

graceful_php_fpm_reload() {
    log "Recargando PHP-FPM sin cortar peticiones activas..."
    exec_app sh -c "kill -USR2 \$(pgrep -o php-fpm) 2>/dev/null || true" || true
    ok "Señal USR2 enviada a PHP-FPM."
}

esperar_cola() {
    log "Señalizando queue worker para reinicio limpio..."
    local antes ahora intento
    antes="$(docker inspect -f '{{.State.StartedAt}}' "$QUEUE_CONTAINER" 2>/dev/null || true)"
    exec_app php artisan queue:restart || true
    intento=0
    while [ "$intento" -lt 60 ]; do
        sleep 1
        ahora="$(docker inspect -f '{{.State.StartedAt}}' "$QUEUE_CONTAINER" 2>/dev/null || true)"
        if [ -n "$ahora" ] && [ "$ahora" != "$antes" ]; then
            ok "Queue worker reiniciado."
            return 0
        fi
        intento=$((intento + 1))
    done
    warn "La cola no mostró un reinicio en 60 s. Se continúa."
}

refresh_permissions() {
    log "Ajustando permisos de storage y bootstrap/cache..."
    exec_app chown -R 1337:1337 /var/www/html/storage /var/www/html/bootstrap/cache
    exec_app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
    ok "Permisos actualizados."
}

preflight_espacio() {
    local disponible necesario
    disponible="$(df -Pk . | awk 'NR==2 {print $4}')"
    if [ -d public/build ]; then
        necesario="$(du -sk public/build | awk '{print $1}')"
    else
        necesario=512000
    fi
    if [ "${disponible:-0}" -lt $((necesario + 204800)) ]; then
        fail "No hay espacio para compilar otro frontend junto a public/build."
    fi
    ok "Espacio en disco suficiente para el build."
}

mysql_root() {
    docker exec "$DB_CONTAINER" sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; mysql --user=root --batch --skip-column-names -e "$1"; unset MYSQL_PWD' sh "$1"
}

cerrar_creacion_de_triggers() {
    mysql_root "SET GLOBAL log_bin_trust_function_creators = 0" || true
    local valor
    valor="$(mysql_root "SELECT @@GLOBAL.log_bin_trust_function_creators" || true)"
    if [ "$valor" != "0" ]; then
        echo "ERROR: log_bin_trust_function_creators quedó en '${valor}'." >&2
        exit 1
    fi
}

migrate_con_triggers() {
    if ! container_running "$DB_CONTAINER"; then
        fail "El contenedor ${DB_CONTAINER} no está corriendo. No se migra."
    fi

    log "Respaldando base de datos y ejecutando migraciones..."
    backup_db_antes_de_migrar

    # MySQL 8 con binlog rechaza CREATE TRIGGER si el usuario no tiene SUPER
    # ni SET_USER_ID (error 1419). No se concede ninguno de esos privilegios:
    # permiten fijar DEFINER ajeno. La opción se abre solo mientras corre migrate
    # y se cierra aunque la migración falle.
    # ponytail: ventana global durante migrate; para quitarla, migrar con un
    # usuario que ya tenga SET_USER_ID y borrar este bloque.
    echo "Abriendo creación de triggers solo para esta migración..."
    mysql_root "SET GLOBAL log_bin_trust_function_creators = 1"
    set +e
    exec_app php artisan migrate --force
    local status=$?
    set -e
    cerrar_creacion_de_triggers
    if [ "$status" -ne 0 ]; then
        fail "La migración falló (${status}). La ventana de triggers ya se cerró."
    fi
    ok "Migraciones aplicadas."
}

compilar_frontend() {
    local cabeza necesita_ci sha
    cabeza="$(git rev-parse HEAD)"
    necesita_ci=false
    if [ "$PREV_SHA" != "$cabeza" ] && ! git diff --quiet "$PREV_SHA" "$cabeza" -- package-lock.json; then
        necesita_ci=true
    fi
    if ! exec_app test -d node_modules; then
        necesita_ci=true
    fi

    if $necesita_ci; then
        log "Instalando dependencias de Node desde el lockfile..."
        exec_app npm ci
    else
        log "package-lock.json sin cambios. Se omite npm ci."
    fi

    log "Compilando assets sin vaciar public/build..."
    sha="$(git rev-parse --short=7 HEAD)"
    exec_app env "DEPLOY_SHA=${sha}" npm run build
    bash "${SCRIPT_DIR}/scripts/verificar-frontend-publicado.sh"
    ok "Frontend compilado y servido por Nginx."
}

purgar_assets() {
    local sha modo
    sha="$(git rev-parse --short=7 HEAD)"
    if $PURGE_APPLY; then
        modo="--apply"
        log "Purgando assets que ya no están en las compilaciones retenidas..."
    else
        modo="--dry-run"
        log "Purga de assets en simulación. Use --purge-assets para borrar."
    fi
    exec_app env "DEPLOY_SHA=${sha}" node scripts/purgar-assets.mjs "$modo"
}

health_host_from_app_url() {
    local url host
    url="$(env_file_value APP_URL)"
    host="$(printf '%s' "$url" | sed -E 's|^[a-zA-Z][a-zA-Z0-9+.-]*://||; s|[:/].*||')"
    if [ -n "$host" ]; then
        printf '%s' "$host"
        return 0
    fi
    printf '%s' 'gelianv.neobash.site'
}

health_check() {
    log "Verificando salud del sistema..."

    if ! container_running "$APP_CONTAINER"; then
        fail "El contenedor ${APP_CONTAINER} no está en ejecución."
    fi

    if ! container_running "$WEB_CONTAINER"; then
        fail "El contenedor ${WEB_CONTAINER} no está en ejecución."
    fi

    local driver
    driver="$(exec_app php artisan tinker --execute="echo config('session.driver');" 2>/dev/null | tail -1)"
    ok "SESSION driver activo: ${driver:-desconocido}"

    local health_host
    health_host="$(health_host_from_app_url)"
    ok "Health check Host: ${health_host}"

    if ! docker exec -i "$WEB_CONTAINER" wget -q -O /dev/null --timeout=15 \
        --header="Host: ${health_host}" \
        "http://127.0.0.1/login"; then
        fail "Health check falló: Nginx no responde 200 en /login (Host: ${health_host})."
    fi

    ok "Health check exitoso: aplicación respondiendo correctamente."
}

# ------------------------------------------------------------------------------
# Flujo principal
# ------------------------------------------------------------------------------

main() {
    parse_args "$@"

    require_command docker
    require_command git
    detect_docker_compose

    log "=== Actualización segura GeliaNV ==="

    if ! container_running "$APP_CONTAINER"; then
        fail "El contenedor ${APP_CONTAINER} no está corriendo. Ejecute primero: ${COMPOSE_BIN_DISPLAY} -f ${COMPOSE_FILE} up -d"
    fi
    if ! container_running "$WEB_CONTAINER"; then
        fail "El contenedor ${WEB_CONTAINER} no está corriendo."
    fi

    if ! $SKIP_GIT; then
        if [ -n "$(git status --porcelain)" ]; then
            fail "El checkout tiene cambios locales. No se despliega encima de un árbol sucio."
        fi
    fi

    preflight_espacio

    PREV_SHA="$(git rev-parse HEAD)"

    if ! $SKIP_GIT; then
        local rama pendiente
        rama="$(git rev-parse --abbrev-ref HEAD)"
        if [ "$rama" = "HEAD" ]; then
            fail "El checkout no tiene rama. No se descarga una actualización en detached HEAD."
        fi
        if [ -z "${GIT_BRANCH:-}" ]; then
            GIT_BRANCH="$rama"
        elif [ "$GIT_BRANCH" != "$rama" ]; then
            fail "El checkout está en ${rama} y GIT_BRANCH=${GIT_BRANCH}. El despliegue no mezcla otra rama."
        fi

        log "Descargando origin/${GIT_BRANCH}..."
        git fetch origin "$GIT_BRANCH"
        pendiente="$(git rev-list --count "HEAD..origin/${GIT_BRANCH}")"
        if [ "$pendiente" -eq 0 ]; then
            warn "origin/${GIT_BRANCH} no tiene commits nuevos. No se descargó código."
        else
            log "Se aplican ${pendiente} commits de origin/${GIT_BRANCH}."
            GIT_EDITOR=true git merge --no-edit "origin/${GIT_BRANCH}"
        fi
        ok "Repositorio en $(git rev-parse --short HEAD) (${GIT_BRANCH})."
    else
        warn "Omitiendo git pull (--skip-git)."
    fi

    if $REBUILD_IMAGE; then
        log "Compilando imagen Docker. Este paso recrea contenedores y corta el servicio..."
        "${COMPOSE[@]}" build app
        "${COMPOSE[@]}" up -d --force-recreate app queue scheduler reverb web
        ok "Imagen reconstruida y contenedores recreados."
    fi

    exec_app git config --global --add safe.directory /var/www/html || true

    log "Actualizando dependencias de Composer..."
    exec_app composer install --no-dev --optimize-autoloader --no-interaction
    ok "Composer actualizado."

    migrate_con_triggers
    compilar_frontend

    log "Regenerando caché sin vaciar la anterior..."
    exec_app php artisan optimize
    ok "Caché de producción regenerada."

    local recrear_app=false
    local recrear_reverb=false
    if env_needs_recreate; then
        recrear_app=true
    fi
    if reverb_needs_recreate; then
        recrear_reverb=true
    fi

    if $recrear_app || $recrear_reverb; then
        confirmar_recreacion
    fi

    if $recrear_app; then
        local servicio
        for servicio in queue scheduler app; do
            log "Recreando ${servicio}..."
            "${COMPOSE[@]}" up -d --force-recreate --no-deps "$servicio"
        done
        ok "Contenedores de aplicación recreados."
    else
        graceful_php_fpm_reload
    fi

    if $recrear_reverb; then
        log "Recreando reverb..."
        "${COMPOSE[@]}" up -d --force-recreate --no-deps reverb
        ok "Reverb recreado."
    fi

    if $recrear_app || $recrear_reverb || $REBUILD_IMAGE; then
        recargar_nginx
    fi

    esperar_cola
    refresh_permissions
    health_check
    purgar_assets

    log "=== Actualización completada sin vaciar el build ni las rutas ==="
    echo ""
    echo "  Los chunks anteriores siguen en public/build/assets."
    echo "  La purga real se pide con --purge-assets."
    echo ""
}

main "$@"
