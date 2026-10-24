#!/bin/bash
# Falla si Nginx no está entregando el manifiesto publicado en public/build.
# Uso, desde la raíz del checkout: bash scripts/verificar-frontend-publicado.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "$ROOT"

APP_CONTAINER="${DOCKER_APP_CONTAINER:-gelianv_app}"
WEB_CONTAINER="${DOCKER_WEB_CONTAINER:-gelianv_web}"

HEALTH_HOST="$(grep -E '^APP_URL=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' | sed -E 's|^[a-zA-Z][a-zA-Z0-9+.-]*://||; s|[:/].*||' || true)"
if [ -z "$HEALTH_HOST" ]; then
    echo "APP_URL no define el host de comprobación." >&2
    exit 1
fi

if docker exec -i "$APP_CONTAINER" test -e /var/www/html/public/hot; then
    echo "public/hot presente: el frontend de desarrollo sigue activo." >&2
    exit 1
fi

mapfile -t ASSETS < <(docker exec -i -w /var/www/html "$APP_CONTAINER" node --input-type=module -e '
import fs from "node:fs";
const manifest = JSON.parse(fs.readFileSync("public/build/manifest.json", "utf8"));
const js = manifest["resources/js/app.jsx"]?.file;
const css = manifest["resources/css/app.css"]?.file;
if (!js || !css) {
    process.stderr.write("El manifiesto no contiene las entradas de la aplicación.\n");
    process.exit(1);
}
for (const file of [js, css]) {
    if (!fs.existsSync("public/build/" + file)) {
        process.stderr.write("Falta el archivo compilado: " + file + "\n");
        process.exit(1);
    }
}
for (const pagina of [
    "resources/js/Pages/PuntoVenta/Pantallas/Sala.jsx",
    "resources/js/Pages/PuntoVenta/Turnos/Recepcion.jsx",
    "resources/js/Pages/PuntoVenta/Turnos/Ventas.jsx",
]) {
    if (!manifest[pagina]?.file) {
        process.stderr.write("El manifiesto no incluye " + pagina + "\n");
        process.exit(1);
    }
}
process.stdout.write(js + "\n" + css + "\n");
')

JS="${ASSETS[0]:-}"
CSS="${ASSETS[1]:-}"
if [ -z "$JS" ] || [ -z "$CSS" ]; then
    echo "No se pudieron leer las entradas del manifiesto." >&2
    exit 1
fi

HTML="$(docker exec -i "$WEB_CONTAINER" wget -qO- --header="Host: ${HEALTH_HOST}" http://127.0.0.1/login)"
printf '%s' "$HTML" | grep -q "$JS" || { echo "El login no referencia ${JS}" >&2; exit 1; }
printf '%s' "$HTML" | grep -q "$CSS" || { echo "El login no referencia ${CSS}" >&2; exit 1; }
printf '%s' "$HTML" | grep -q 'name="gelia-deploy-sala" content="dev"' && { echo "gelia-deploy-sala sigue en dev." >&2; exit 1; }
printf '%s' "$HTML" | grep -q 'name="gelia-deploy-turnos" content="dev"' && { echo "gelia-deploy-turnos sigue en dev." >&2; exit 1; }

if ! docker exec -i "$WEB_CONTAINER" wget -q -O /dev/null --timeout=15 \
    --header="Host: ${HEALTH_HOST}" \
    "http://127.0.0.1/build/${JS}"; then
    echo "Nginx no entrega /build/${JS}" >&2
    exit 1
fi

echo "Frontend publicado y servido: ${JS}"
