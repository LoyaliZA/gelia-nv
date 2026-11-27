#!/usr/bin/env bash
# Extrae hosts de APP_URL / FORM_PUBLIC_URL y renderiza el template de Nginx.
# Uso:
#   source scripts/nginx-vhosts-from-env.sh
#   ./scripts/nginx-vhosts-from-env.sh [template] [env-file] > rendered.conf
set -euo pipefail

gelia_host_from_url() {
    local url="${1:-}"
    url="${url#\"}"
    url="${url%\"}"
    url="${url#\'}"
    url="${url%\'}"
    url="${url#"${url%%[![:space:]]*}"}"
    url="${url%"${url##*[![:space:]]}"}"
    url="${url#*://}"
    url="${url%%/*}"
    url="${url%%:*}"
    printf '%s' "$url"
}

gelia_env_file_value() {
    local key="$1"
    local file="${2:-.env}"
    local line val
    line="$(grep -E "^${key}=" "$file" 2>/dev/null | head -1 || true)"
    val="${line#*=}"
    val="${val#\"}"
    val="${val%\"}"
    val="${val#\'}"
    val="${val%\'}"
    printf '%s' "$val"
}

gelia_nginx_validate_host() {
    local host="$1"
    local label="$2"
    if [[ ! "$host" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]]; then
        printf 'Host inválido para %s: %s\n' "$label" "$host" >&2
        return 1
    fi
}

gelia_nginx_hosts_from_env() {
    local env_file="${1:-.env}"
    GELIA_NGINX_APP_HOST="$(gelia_host_from_url "$(gelia_env_file_value APP_URL "$env_file")")"
    GELIA_NGINX_FORM_HOST="$(gelia_host_from_url "$(gelia_env_file_value FORM_PUBLIC_URL "$env_file")")"
    gelia_nginx_validate_host "$GELIA_NGINX_APP_HOST" "APP_URL"
    gelia_nginx_validate_host "$GELIA_NGINX_FORM_HOST" "FORM_PUBLIC_URL"
    if [[ "$GELIA_NGINX_APP_HOST" == "$GELIA_NGINX_FORM_HOST" ]]; then
        printf 'APP_URL y FORM_PUBLIC_URL no pueden compartir el mismo host.\n' >&2
        return 1
    fi
}

gelia_nginx_render() {
    local template="$1"
    local app_host="$2"
    local form_host="$3"
    sed -e "s|__GELIA_APP_HOST__|${app_host}|g" \
        -e "s|__GELIA_FORM_HOST__|${form_host}|g" \
        "$template"
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    cd "$ROOT"
    TEMPLATE="${1:-default.conf}"
    ENV_FILE="${2:-.env}"
    gelia_nginx_hosts_from_env "$ENV_FILE"
    gelia_nginx_render "$TEMPLATE" "$GELIA_NGINX_APP_HOST" "$GELIA_NGINX_FORM_HOST"
fi
