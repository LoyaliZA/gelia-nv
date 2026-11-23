#!/bin/bash
# Actualización rápida en prod simulado (túnel Cloudflare).
# Conserva default.conf u otros cambios locales del servidor; hace pull, build y caché.
# Uso: ./update-prod-sim.sh
#      ./update-prod-sim.sh --skip-git   # Solo aplicar en contenedores (sin fetch)
set -euo pipefail
cd "$(dirname "$0")"
chmod +x update-safe.sh 2>/dev/null || true
exec ./update-safe.sh --allow-local --refresh-cache --yes "$@"
