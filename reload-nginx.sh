#!/bin/bash
# Recarga Nginx tras un cambio de vhost o de IP de PHP-FPM / Reverb.
#
# Los server_name salen de APP_URL y FORM_PUBLIC_URL en .env (no de hosts fijos).
#
# Producción:
#   COMPOSE_ARGS="-f docker-compose.prod.yaml" ./reload-nginx.sh
#
# GELIA Test:
#   COMPOSE_ARGS="-f docker-compose.prod.yaml -f docker-compose.test.yaml" \
#   ./reload-nginx.sh
#
# El valor por defecto es default.conf y docker-compose.prod.yaml.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# shellcheck source=scripts/nginx-vhosts-from-env.sh
source "${SCRIPT_DIR}/scripts/nginx-vhosts-from-env.sh"

NGINX_CONF="${NGINX_CONF:-default.conf}"
WEB_CONTAINER="${DOCKER_WEB_CONTAINER:-gelianv_web}"
ENV_FILE="${GELIA_ENV_FILE:-.env}"

if [[ -n "${COMPOSE_ARGS:-}" ]]; then
    # COMPOSE_ARGS es una lista de argumentos de compose, no un solo archivo.
    read -r -a COMPOSE_ARG_ARRAY <<< "$COMPOSE_ARGS"
else
    COMPOSE_ARG_ARRAY=(-f docker-compose.prod.yaml)
fi

log()  { printf '\n[%s] %s\n' "$(date '+%H:%M:%S')" "$*"; }
ok()   { printf '[%s] ✓ %s\n' "$(date '+%H:%M:%S')" "$*"; }
fail() { printf '[%s] ✗ %s\n' "$(date '+%H:%M:%S')" "$*" >&2; exit 1; }

case "$PWD" in
    /home/ti-admin/gelianv)
        fail "Este workspace es Sail. No recarga gelianv_web."
        ;;
esac

if [[ ! -f "$NGINX_CONF" ]]; then
    fail "No se encontró ${NGINX_CONF} en ${SCRIPT_DIR}."
fi

if ! grep -q '__GELIA_APP_HOST__' "$NGINX_CONF" \
    || ! grep -q '__GELIA_FORM_HOST__' "$NGINX_CONF"; then
    fail "${NGINX_CONF} debe declarar __GELIA_APP_HOST__ y __GELIA_FORM_HOST__ (se rellenan desde .env)."
fi

if [[ ! -f "$ENV_FILE" ]]; then
    fail "No se encontró ${ENV_FILE}."
fi

gelia_nginx_hosts_from_env "$ENV_FILE"

case "$PWD" in
    /home/ti-admin/produccion-test|/home/ti-admin/gelia-nv-test)
        if [[ "$GELIA_NGINX_APP_HOST" == "gelianv.neobash.site" \
            || "$GELIA_NGINX_FORM_HOST" == "form.neobash.site" ]]; then
            fail "Este workspace es Test. APP_URL/FORM_PUBLIC_URL no deben ser los de producción."
        fi
        ;;
esac

if ! docker inspect -f '{{.State.Running}}' "$WEB_CONTAINER" 2>/dev/null | grep -q true; then
    fail "El contenedor ${WEB_CONTAINER} no está en ejecución."
fi

RENDERED="$(mktemp)"
trap 'rm -f "$RENDERED"' EXIT
gelia_nginx_render "$NGINX_CONF" "$GELIA_NGINX_APP_HOST" "$GELIA_NGINX_FORM_HOST" > "$RENDERED"

if grep -q '__GELIA_APP_HOST__\|__GELIA_FORM_HOST__' "$RENDERED"; then
    fail "Tras renderizar, ${NGINX_CONF} aún tiene marcadores de host."
fi
grep -q "server_name ${GELIA_NGINX_APP_HOST};" "$RENDERED" \
    || fail "El config renderizado no declara server_name ${GELIA_NGINX_APP_HOST}."
grep -q "server_name ${GELIA_NGINX_FORM_HOST};" "$RENDERED" \
    || fail "El config renderizado no declara server_name ${GELIA_NGINX_FORM_HOST}."

log "Sincronizando vhosts (${GELIA_NGINX_APP_HOST}, ${GELIA_NGINX_FORM_HOST}) a ${WEB_CONTAINER}..."
HOST_MD5="$(md5sum "$RENDERED" | awk '{print $1}')"
docker exec -i "$WEB_CONTAINER" sh -c 'cat > /etc/nginx/conf.d/default.conf' < "$RENDERED"
CTR_MD5="$(docker exec -i "$WEB_CONTAINER" md5sum /etc/nginx/conf.d/default.conf | awk '{print $1}')"
if [ "$HOST_MD5" != "$CTR_MD5" ]; then
    fail "Tras sincronizar, host ($HOST_MD5) y contenedor ($CTR_MD5) no coinciden."
fi
ok "Config renderizado sincronizado (md5=${CTR_MD5})."

log "Validando configuración de Nginx..."
if ! docker exec -i "$WEB_CONTAINER" nginx -t; then
    fail "nginx -t falló. Revise ${NGINX_CONF} y APP_URL/FORM_PUBLIC_URL antes de recargar."
fi
ok "Configuración válida."

log "Recargando Nginx en caliente (${WEB_CONTAINER})..."
if docker exec -i "$WEB_CONTAINER" nginx -s reload; then
    ok "Nginx recargado (vhosts desde APP_URL/FORM_PUBLIC_URL, sin reiniciar el contenedor)."
    exit 0
fi

printf '[%s] ⚠ %s\n' "$(date '+%H:%M:%S')" "Reload falló; reiniciando servicio web por Compose..." >&2

if docker compose version >/dev/null 2>&1; then
    docker compose "${COMPOSE_ARG_ARRAY[@]}" restart web
elif command -v docker-compose >/dev/null 2>&1; then
    docker-compose "${COMPOSE_ARG_ARRAY[@]}" restart web
else
    fail "No se pudo recargar ni reiniciar: falta docker compose."
fi

sleep 2
ok "Servicio web reiniciado."
