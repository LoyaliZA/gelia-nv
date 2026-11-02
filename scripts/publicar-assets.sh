#!/bin/bash
# Publica un build de Vite sin borrar los chunks que ya sirven las pestañas abiertas.
# El manifiesto se reemplaza al final, con mv, cuando los archivos nuevos ya están en destino.
#
# Uso, desde la raíz del checkout:
#   DEPLOY_SHA=abc1234 bash scripts/publicar-assets.sh
set -euo pipefail

STAGE="${VITE_STAGE_DIR:-public/build-next}"
DEST="${VITE_BUILD_DIR:-public/build}"

if [ ! -f "${STAGE}/manifest.json" ]; then
    echo "Falta ${STAGE}/manifest.json. El build no se publica." >&2
    exit 1
fi

sha="${DEPLOY_SHA:-}"
if [ -z "$sha" ]; then
    sha="$(git rev-parse --short=7 HEAD 2>/dev/null || echo nogit00)"
fi
sha7="$(printf '%s' "$sha" | tr -cd 'A-Za-z0-9' | cut -c1-7)"
if [ -z "$sha7" ]; then
    sha7="nogit00"
fi

mkdir -p "${DEST}/assets" "${DEST}/manifests"

if [ -d "${STAGE}/assets" ]; then
    cp -an "${STAGE}/assets/." "${DEST}/assets/"
fi

shopt -s dotglob nullglob
for entrada in "${STAGE}"/*; do
    base="$(basename "$entrada")"
    if [ "$base" = "manifest.json" ] || [ "$base" = "assets" ]; then
        continue
    fi
    if [ -d "$entrada" ]; then
        mkdir -p "${DEST}/${base}"
        cp -an "${entrada}/." "${DEST}/${base}/"
    elif [ ! -e "${DEST}/${base}" ]; then
        cp -a "$entrada" "${DEST}/${base}"
    fi
done
shopt -u dotglob nullglob

cp "${STAGE}/manifest.json" "${DEST}/manifest.json.tmp"
mv -f "${DEST}/manifest.json.tmp" "${DEST}/manifest.json"

sello="$(date -u +%Y%m%dT%H%M%SZ)"
cp "${DEST}/manifest.json" "${DEST}/manifests/${sello}-${sha7}.json"

rm -rf "$STAGE"
echo "Assets publicados. Manifiesto ${sello}-${sha7}.json"
