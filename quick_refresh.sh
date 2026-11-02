#!/bin/bash
# Antes era un atajo sin respaldo ni set -e. Ahora es el mismo despliegue seguro.
# --quick no omite composer, respaldo ni verificación.
set -euo pipefail
cd "$(dirname "$0")"
exec ./update-safe.sh "$@"
