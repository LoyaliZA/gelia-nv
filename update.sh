#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/backup-db.sh
source "${SCRIPT_DIR}/scripts/backup-db.sh"

# ==============================================================================
# Script de Actualización Automática - GeliaNV
# ==============================================================================

# 1. Obtener cambios de GitHub (sin abrir editor de merge commit)
echo "Descargando actualizaciones desde el repositorio..."
GIT_EDITOR=true git pull --no-edit origin main

# 2. Sincronizar permisos y excepciones de Git (Preventivo)
docker exec -i gelianv_app git config --global --add safe.directory /var/www/html

# 3. Limpiar caché ANTES de todo (evita servir rutas viejas durante el deploy)
echo "Limpiando caché anterior..."
docker exec -i gelianv_app php artisan optimize:clear

# 4. Actualizar dependencias de PHP (Laravel)
echo "Actualizando dependencias de Composer..."
docker exec -i gelianv_app composer install --no-dev --optimize-autoloader --no-interaction

# 5. Ejecutar migraciones de base de datos
echo "Sincronizando estructura de base de datos..."
backup_db_antes_de_migrar

# MySQL 8 con binlog rechaza CREATE TRIGGER si el usuario no tiene SUPER
# ni SET_USER_ID (error 1419). No se concede ninguno de esos privilegios:
# permiten fijar DEFINER ajeno. La opción se abre solo mientras corre migrate
# y se cierra aunque la migración falle.
# ponytail: ventana global durante migrate; para quitarla, migrar con un
# usuario que ya tenga SET_USER_ID y borrar este bloque.
mysql_root() {
  docker exec gelianv_db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; mysql --user=root --batch --skip-column-names -e "$1"; unset MYSQL_PWD' sh "$1"
}
cerrar_creacion_de_triggers() {
  mysql_root "SET GLOBAL log_bin_trust_function_creators = 0" || true
  local valor
  valor="$(mysql_root "SELECT @@GLOBAL.log_bin_trust_function_creators" || true)"
  if [ "$valor" != "0" ]; then
    echo "ERROR: log_bin_trust_function_creators quedó en '${valor}'." >&2
    exit 1
  fi
}
echo "Abriendo creación de triggers solo para esta migración..."
mysql_root "SET GLOBAL log_bin_trust_function_creators = 1"
trap 'status=$?; cerrar_creacion_de_triggers; exit "$status"' EXIT
docker exec -i gelianv_app php artisan migrate --force
trap - EXIT
cerrar_creacion_de_triggers

# 6. Reconstruir assets del frontend (Vite)
echo "Compilando nueva versión del frontend..."
docker exec -i -w /var/www/html gelianv_app npm ci
docker exec -i -w /var/www/html gelianv_app npm run build
bash "${SCRIPT_DIR}/scripts/verificar-frontend-publicado.sh"

# ==============================================================================
# 7. Regenerar caché con código ya actualizado
# ==============================================================================
echo "Regenerando caché de rutas, eventos y optimización..."
docker exec -i gelianv_app php artisan route:cache
docker exec -i gelianv_app php artisan event:cache
docker exec -i gelianv_app php artisan optimize

# ==============================================================================
# 8. Refrescar procesos en memoria con código nuevo
# ==============================================================================

# --- App (PHP-FPM): graceful reload, no corta requests activos ---
echo "Recargando PHP-FPM (OPcache)..."
docker exec -i gelianv_app sh -c "kill -USR2 \$(pgrep php-fpm | head -1) 2>/dev/null || true"

# --- Queue Worker: señal limpia, Docker lo relanza solo por restart: unless-stopped ---
echo "Señalizando queue worker para reinicio limpio..."
docker exec -i gelianv_app php artisan queue:restart
sleep 8  # Tiempo para que el worker termine su job actual y Docker lo relance

# --- Scheduler y Reverb: no se tocan ---
# El scheduler lee el código de disco en cada tick, ya tiene el código nuevo
# Reverb NO se reinicia para mantener WebSockets activos

# ==============================================================================
# 9. Refrescar permisos de almacenamiento
# ==============================================================================
echo "Ajustando permisos de archivos..."
docker exec -i gelianv_app chown -R 1337:1337 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -i gelianv_app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "--- Proceso de Actualización Finalizado ---"