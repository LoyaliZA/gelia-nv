#!/bin/bash
# Envoltorio del despliegue. La ventana de triggers y el build sin borrar
# chunks viven en update-safe.sh.
set -euo pipefail
cd "$(dirname "$0")"
exec ./update-safe.sh "$@"
