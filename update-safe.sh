#!/bin/bash
# ==============================================================================
# Actualización segura de GeliaNV — minimiza caídas y errores 502
#
# Uso:
#   ./update-safe.sh              # Actualización estándar (git pull + deploy)
#   ./update-safe.sh --skip-git   # Sin git pull (solo aplicar cambios locales)
#   ./update-safe.sh --rebuild    # Recompila imagen Docker y recrea contenedores
#   ./update-safe.sh --recreate-env  # Fuerza recarga de variables .env en contenedores
#
# Qué hace diferente a update.sh:
#   - Detecta cambios en .env y recrea contenedores (evita SESSION_DRIVER desincronizado)
#   - Reinicia Nginx (web) tras recrear app/reverb (evita 502 por IP en caché)
#   - Regenera caché de Laravel DESPUÉS de sincronizar el entorno
#   - Verificación de salud al finalizar
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# shellcheck source=scripts/backup-db.sh
source "${SCRIPT_DIR}/scripts/backup-db.sh"

COMPOSE_FILE="docker-compose.prod.yaml"
COMPOSE=()
COMPOSE_BIN_DISPLAY=""

APP_CONTAINER="${DOCKER_APP_CONTAINER:-gelianv_app}"
WEB_CONTAINER="${DOCKER_WEB_CONTAINER:-gelianv_web}"
QUEUE_WAIT_SECONDS="${QUEUE_WAIT_SECONDS:-8}"
GIT_BRANCH="${GIT_BRANCH:-main}"

SKIP_GIT=false
REBUILD_IMAGE=false
FORCE_RECREATE_ENV=false

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
    sed -n '4,10p' "$0" | sed 's/^# \{0,1\}//'
    exit 0
}

parse_args() {
    for arg in "$@"; do
        case "$arg" in
            --skip-git)       SKIP_GIT=true ;;
            --rebuild)        REBUILD_IMAGE=true ;;
            --recreate-env)   FORCE_RECREATE_ENV=true ;;
            -h|--help)        usage ;;
            *)
                fail "Opción desconocida: ${arg}. Use --help."
                ;;
        esac
    done
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

    # #region agent log
    local debug_log="${SCRIPT_DIR}/.cursor/debug-8c8146.log"
    mkdir -p "${SCRIPT_DIR}/.cursor"
    printf '%s\n' "{\"sessionId\":\"8c8146\",\"runId\":\"post-fix\",\"hypothesisId\":\"A\",\"location\":\"update-safe.sh:detect_docker_compose\",\"message\":\"compose command detected\",\"data\":{\"compose_bin\":\"${COMPOSE_BIN_DISPLAY}\"},\"timestamp\":$(($(date +%s)*1000))}" >> "$debug_log"
    # #endregion

    ok "Docker Compose detectado: ${COMPOSE_BIN_DISPLAY}"
}

container_running() {
    docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null | grep -q true
}

exec_app() {
    docker exec -i "$APP_CONTAINER" "$@"
}

env_file_value() {
    local key="$1"
    grep -E "^${key}=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' || true
}

container_env_value() {
    local key="$1"
    docker exec -i "$APP_CONTAINER" printenv "$key" 2>/dev/null || true
}

env_needs_recreate() {
    if $FORCE_RECREATE_ENV; then
        return 0
    fi

    if [ ! -f .env ]; then
        return 1
    fi

    # Si .env es más reciente que el arranque del contenedor, probablemente cambió
    local env_mtime container_start_epoch
    env_mtime="$(stat -c %Y .env 2>/dev/null || echo 0)"
    container_start_epoch="$(docker inspect -f '{{.State.StartedAt}}' "$APP_CONTAINER" 2>/dev/null | xargs -I{} date -d "{}" +%s 2>/dev/null || echo 0)"
    if [ "$env_mtime" -gt "$container_start_epoch" ]; then
        warn ".env modificado después del último arranque de ${APP_CONTAINER}."
        return 0
    fi

    # Comparar variables críticas entre archivo y proceso del contenedor
    local keys=(SESSION_DRIVER APP_ENV APP_URL CACHE_STORE QUEUE_CONNECTION)
    for key in "${keys[@]}"; do
        local file_val container_val
        file_val="$(env_file_value "$key")"
        container_val="$(container_env_value "$key")"
        if [ -n "$file_val" ] && [ "$file_val" != "$container_val" ]; then
            warn "Desincronización detectada: ${key} (.env=${file_val}, contenedor=${container_val:-<vacío>})"
            return 0
        fi
    done

    return 1
}

recreate_runtime_containers() {
    log "Recreando contenedores para sincronizar variables de entorno..."
    "${COMPOSE[@]}" up -d --force-recreate --no-deps app queue scheduler reverb
    ok "Contenedores app/queue/scheduler/reverb recreados."
}

restart_web_proxy() {
    log "Reiniciando Nginx (${WEB_CONTAINER}) para refrescar upstreams..."
    "${COMPOSE[@]}" restart web
    sleep 2
    ok "Nginx reiniciado."
}

graceful_php_fpm_reload() {
    log "Recargando PHP-FPM sin cortar peticiones activas..."
    exec_app sh -c "kill -USR2 \$(pgrep -o php-fpm) 2>/dev/null || true" || true
    ok "Señal USR2 enviada a PHP-FPM."
}

restart_queue_worker() {
    log "Señalizando queue worker para reinicio limpio..."
    exec_app php artisan queue:restart || true
    sleep "$QUEUE_WAIT_SECONDS"
    ok "Queue worker reiniciado (espera ${QUEUE_WAIT_SECONDS}s)."
}

refresh_permissions() {
    log "Ajustando permisos de storage y bootstrap/cache..."
    exec_app chown -R 1337:1337 /var/www/html/storage /var/www/html/bootstrap/cache
    exec_app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
    ok "Permisos actualizados."
}

# Host HTTP para Nginx virtual hosts (APP_URL). Evita el default_server 404.
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

    # Nginx enruta por server_name; sin Host correcto cae en default_server → 404.
    local health_host
    health_host="$(health_host_from_app_url)"
    ok "Health check Host: ${health_host}"

    if ! docker exec -i "$WEB_CONTAINER" wget -qSO- --timeout=15 \
        --header="Host: ${health_host}" \
        "http://127.0.0.1/login" 2>&1 | grep -q "200 OK"; then
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

    # 0. Pre-flight
    if ! container_running "$APP_CONTAINER"; then
        fail "El contenedor ${APP_CONTAINER} no está corriendo. Ejecute primero: ${COMPOSE_BIN_DISPLAY} -f ${COMPOSE_FILE} up -d"
    fi

    # 1. Código fuente
    if ! $SKIP_GIT; then
        log "Descargando cambios desde origin/${GIT_BRANCH}..."
        git pull origin "$GIT_BRANCH"
        ok "Repositorio actualizado."
    else
        warn "Omitiendo git pull (--skip-git)."
    fi

    # 2. Rebuild de imagen (opcional)
    if $REBUILD_IMAGE; then
        log "Compilando imagen Docker (puede tardar varios minutos)..."
        "${COMPOSE[@]}" build app
        "${COMPOSE[@]}" up -d --force-recreate app queue scheduler reverb web
        ok "Imagen reconstruida y contenedores recreados."
    fi

    # 3. Git safe directory (preventivo)
    exec_app git config --global --add safe.directory /var/www/html || true

    # 4. Limpiar caché ANTES de actualizar (evita servir config/rutas viejas)
    log "Limpiando caché anterior..."
    exec_app php artisan optimize:clear
    ok "Caché limpiada."

    # 5. Dependencias PHP
    log "Actualizando dependencias de Composer..."
    exec_app composer install --no-dev --optimize-autoloader --no-interaction
    ok "Composer actualizado."

    # 6. Migraciones con respaldo
    log "Respaldando base de datos y ejecutando migraciones..."
    backup_db_antes_de_migrar
    exec_app php artisan migrate --force
    ok "Migraciones aplicadas."

    # 7. Frontend
    log "Compilando assets del frontend (Vite)..."
    exec_app npm install --no-audit --no-fund
    exec_app npm run build
    ok "Frontend compilado."

    # 8. Sincronizar entorno Docker ↔ .env (crítico para SESSION_DRIVER, etc.)
    local did_recreate=false
    if env_needs_recreate; then
        recreate_runtime_containers
        did_recreate=true
    fi

    # 9. Regenerar caché con código y entorno ya actualizados
    log "Regenerando caché de configuración y rutas..."
    exec_app php artisan optimize:clear
    exec_app php artisan optimize
    ok "Caché de producción regenerada."

    # 10. Reiniciar proxy si hubo recreación (evita 502 por IP obsoleta en Nginx)
    if $did_recreate || $REBUILD_IMAGE; then
        restart_web_proxy
    else
        graceful_php_fpm_reload
    fi

    # 11. Queue worker
    restart_queue_worker

    # 12. Permisos
    refresh_permissions

    # 13. Verificación final
    health_check

    log "=== Actualización completada sin interrupciones críticas ==="
    echo ""
    echo "  Si cambió .env: los contenedores ya están sincronizados."
    echo "  Si algo falla: use ./rollback.sh para revertir el código."
    echo ""
}

main "$@"
