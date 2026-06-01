#!/bin/bash
# Respaldo de BD antes de migraciones — incluir con: source "$(dirname "$0")/scripts/backup-db.sh"
#
# Variables opcionales:
#   DOCKER_APP_CONTAINER  contenedor PHP (default: gelianv_app)

backup_db_antes_de_migrar() {
    local container="${DOCKER_APP_CONTAINER:-gelianv_app}"

    echo "=============================================="
    echo " Respaldo de base de datos (pre-migración)"
    echo " Contenedor: ${container}"
    echo "=============================================="

    if ! docker exec -i "$container" php artisan db:backup; then
        echo "ERROR: No se pudo crear el respaldo. Migración abortada." >&2
        exit 1
    fi

    echo "Respaldo completado."
    echo ""
}
