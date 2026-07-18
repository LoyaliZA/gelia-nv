#!/bin/bash
# ==============================================================================
# Recarga Nginx tras cambios en default.conf (Cloudflare IP, vhosts, etc.)
#
# Uso (después de git pull / update-safe si cambió default.conf):
#   ./reload-nginx.sh
#
# Qué hace:
#   1. Valida la config montada (nginx -t)
#   2. Recarga en caliente (nginx -s reload) — sin tumbar el contenedor
#   3. Si el reload falla, reinicia el servicio web por Compose
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

COMPOSE_FILE="docker-compose.prod.yaml"
WEB_CONTAINER="${DOCKER_WEB_CONTAINER:-gelianv_web}"

log()  { printf '\n[%s] %s\n' "$(date '+%H:%M:%S')" "$*"; }
ok()   { printf '[%s] ✓ %s\n' "$(date '+%H:%M:%S')" "$*"; }
fail() { printf '[%s] ✗ %s\n' "$(date '+%H:%M:%S')" "$*" >&2; exit 1; }

if ! docker inspect -f '{{.State.Running}}' "$WEB_CONTAINER" 2>/dev/null | grep -q true; then
    fail "El contenedor ${WEB_CONTAINER} no está en ejecución."
fi

if [ ! -f default.conf ]; then
    fail "No se encontró default.conf en ${SCRIPT_DIR}."
fi

log "Validando configuración de Nginx..."
if ! docker exec -i "$WEB_CONTAINER" nginx -t; then
    fail "nginx -t falló. Revise default.conf antes de recargar."
fi
ok "Configuración válida."

log "Recargando Nginx en caliente (${WEB_CONTAINER})..."
if docker exec -i "$WEB_CONTAINER" nginx -s reload; then
    ok "Nginx recargado (default.conf activo sin reiniciar el contenedor)."
    exit 0
fi

warn_msg="Reload falló; reiniciando servicio web por Compose..."
printf '[%s] ⚠ %s\n' "$(date '+%H:%M:%S')" "$warn_msg" >&2

if docker compose version >/dev/null 2>&1; then
    docker compose -f "$COMPOSE_FILE" restart web
elif command -v docker-compose >/dev/null 2>&1; then
    docker-compose -f "$COMPOSE_FILE" restart web
else
    fail "No se pudo recargar ni reiniciar: falta docker compose."
fi

sleep 2
ok "Servicio web reiniciado."
