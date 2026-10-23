#!/bin/bash
# Recarga Nginx tras un cambio de vhost o de IP de PHP-FPM / Reverb.
#
# Producción (checkout que no sea Sail ni GELIA-NV Test):
#   NGINX_CONF=default.conf COMPOSE_ARGS="-f docker-compose.prod.yaml" ./reload-nginx.sh
#
# GELIA-NV Test:
#   NGINX_CONF=default.test.conf \
#   COMPOSE_ARGS="-f docker-compose.prod.yaml -f docker-compose.test.yaml" \
#   ./reload-nginx.sh
#
# El valor por defecto sigue siendo default.conf y docker-compose.prod.yaml.
# En /home/ti-admin/gelia-nv-test y en el workspace Sail ese valor por defecto se rechaza.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

NGINX_CONF="${NGINX_CONF:-default.conf}"
WEB_CONTAINER="${DOCKER_WEB_CONTAINER:-gelianv_web}"

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
    /home/ti-admin/gelia-nv-test)
        if [[ "$NGINX_CONF" != "default.test.conf" ]]; then
            fail "En GELIA-NV Test NGINX_CONF debe ser default.test.conf."
        fi
        ;;
esac

if [[ ! -f "$NGINX_CONF" ]]; then
    fail "No se encontró ${NGINX_CONF} en ${SCRIPT_DIR}."
fi

if [[ "$NGINX_CONF" == "default.test.conf" ]]; then
    grep -q 'server_name gelianvtest\.neobash\.site' "$NGINX_CONF" \
        || fail "default.test.conf no declara gelianvtest.neobash.site."
    grep -q 'server_name formtest\.neobash\.site' "$NGINX_CONF" \
        || fail "default.test.conf no declara formtest.neobash.site."
    if grep -E 'server_name gelianv\.neobash\.site|server_name form\.neobash\.site' "$NGINX_CONF"; then
        fail "default.test.conf contiene dominios de producción."
    fi
fi

if [[ "$NGINX_CONF" == "default.conf" ]]; then
    grep -q 'server_name gelianv\.neobash\.site' "$NGINX_CONF" \
        || fail "default.conf no declara el dominio de producción."
    if grep -E 'server_name gelianvtest\.neobash\.site|server_name formtest\.neobash\.site' "$NGINX_CONF"; then
        fail "default.conf contiene dominios de Test."
    fi
fi

if ! docker inspect -f '{{.State.Running}}' "$WEB_CONTAINER" 2>/dev/null | grep -q true; then
    fail "El contenedor ${WEB_CONTAINER} no está en ejecución."
fi

log "Sincronizando ${NGINX_CONF} al inode montado en ${WEB_CONTAINER}..."
HOST_MD5="$(md5sum "$NGINX_CONF" | awk '{print $1}')"
docker exec -i "$WEB_CONTAINER" sh -c 'cat > /etc/nginx/conf.d/default.conf' < "$NGINX_CONF"
CTR_MD5="$(docker exec -i "$WEB_CONTAINER" md5sum /etc/nginx/conf.d/default.conf | awk '{print $1}')"
if [ "$HOST_MD5" != "$CTR_MD5" ]; then
    fail "Tras sincronizar, host ($HOST_MD5) y contenedor ($CTR_MD5) no coinciden."
fi
ok "${NGINX_CONF} sincronizado (md5=${CTR_MD5})."

log "Validando configuración de Nginx..."
if ! docker exec -i "$WEB_CONTAINER" nginx -t; then
    fail "nginx -t falló. Revise ${NGINX_CONF} antes de recargar."
fi
ok "Configuración válida."

log "Recargando Nginx en caliente (${WEB_CONTAINER})..."
if docker exec -i "$WEB_CONTAINER" nginx -s reload; then
    ok "Nginx recargado (${NGINX_CONF} activo sin reiniciar el contenedor)."
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
