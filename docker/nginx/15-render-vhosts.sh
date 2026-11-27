#!/bin/sh
# Rellena /etc/nginx/conf.d/default.conf desde APP_URL y FORM_PUBLIC_URL.
# Corre en docker-entrypoint.d (nginx:alpine) como root, antes de nginx -t.
set -eu

ENV_FILE="${GELIA_ENV_FILE:-/var/www/html/.env}"
TEMPLATE="${GELIA_NGINX_TEMPLATE:-/var/www/html/default.conf}"
OUTPUT="/etc/nginx/conf.d/default.conf"

env_value() {
    key="$1"
    val=$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- || true)
    val=$(printf '%s' "$val" | tr -d "\"'")
    printf '%s' "$val"
}

host_from_url() {
    url="$1"
    url=${url#*://}
    url=${url%%/*}
    url=${url%%:*}
    printf '%s' "$url"
}

host_ok() {
    case "$1" in
        ''|*[!A-Za-z0-9.-]*) return 1 ;;
        -*|.*|*-|*. ) return 1 ;;
        *) return 0 ;;
    esac
}

if [ ! -f "$TEMPLATE" ]; then
    echo "GELIA nginx: no está el template ${TEMPLATE}" >&2
    exit 1
fi

APP_URL_VAL="${APP_URL:-}"
FORM_URL_VAL="${FORM_PUBLIC_URL:-}"
if [ -f "$ENV_FILE" ]; then
    [ -n "$APP_URL_VAL" ] || APP_URL_VAL=$(env_value APP_URL)
    [ -n "$FORM_URL_VAL" ] || FORM_URL_VAL=$(env_value FORM_PUBLIC_URL)
fi

APP_HOST=$(host_from_url "$APP_URL_VAL")
FORM_HOST=$(host_from_url "$FORM_URL_VAL")

if ! host_ok "$APP_HOST"; then
    echo "GELIA nginx: APP_URL no tiene un host válido (${APP_URL_VAL})" >&2
    exit 1
fi
if ! host_ok "$FORM_HOST"; then
    echo "GELIA nginx: FORM_PUBLIC_URL no tiene un host válido (${FORM_URL_VAL})" >&2
    exit 1
fi
if [ "$APP_HOST" = "$FORM_HOST" ]; then
    echo "GELIA nginx: APP_URL y FORM_PUBLIC_URL no pueden compartir el mismo host" >&2
    exit 1
fi

sed -e "s|__GELIA_APP_HOST__|${APP_HOST}|g" \
    -e "s|__GELIA_FORM_HOST__|${FORM_HOST}|g" \
    "$TEMPLATE" > "$OUTPUT"

if grep -q '__GELIA_APP_HOST__\|__GELIA_FORM_HOST__' "$OUTPUT"; then
    echo "GELIA nginx: el template no sustituyó los marcadores de host" >&2
    exit 1
fi

echo "GELIA nginx: server_name app=${APP_HOST} form=${FORM_HOST}"
