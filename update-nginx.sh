#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# shellcheck source=scripts/backup-db.sh
source "${SCRIPT_DIR}/scripts/backup-db.sh"

# ==============================================================================
# Actualización de producción CON recarga de Nginx
#
# Usar cuando el deploy incluye cambios en default.conf (IPs Cloudflare, vhosts,
# proxy, etc.). Equivalente a update.sh + reload de Nginx.
#
# Uso:
#   ./update-nginx.sh
# ==============================================================================

APP_CONTAINER="${DOCKER_APP_CONTAINER:-gelianv_app}"
GIT_BRANCH="${GIT_BRANCH:-main}"

echo "=== Actualización GeliaNV (con Nginx) ==="

# 1. Obtener cambios de GitHub
echo "Descargando actualizaciones desde el repositorio..."
git pull origin "$GIT_BRANCH"

# 2. Sincronizar permisos y excepciones de Git (Preventivo)
docker exec -i "$APP_CONTAINER" git config --global --add safe.directory /var/www/html

# 3. Limpiar caché ANTES de todo (evita servir rutas viejas durante el deploy)
echo "Limpiando caché anterior..."
docker exec -i "$APP_CONTAINER" php artisan optimize:clear

# 4. Actualizar dependencias de PHP (Laravel)
echo "Actualizando dependencias de Composer..."
docker exec -i "$APP_CONTAINER" composer install --no-dev --optimize-autoloader --no-interaction

# 5. Ejecutar migraciones de base de datos
echo "Sincronizando estructura de base de datos..."
backup_db_antes_de_migrar
docker exec -i "$APP_CONTAINER" php artisan migrate --force

# 6. Reconstruir assets del frontend (Vite)
echo "Compilando nueva versión del frontend..."
docker exec -i "$APP_CONTAINER" npm install
docker exec -i "$APP_CONTAINER" npm run build

# 7. Regenerar caché con código ya actualizado
echo "Regenerando caché de rutas y optimización..."
docker exec -i "$APP_CONTAINER" php artisan route:cache
docker exec -i "$APP_CONTAINER" php artisan optimize

# 8. Refrescar procesos en memoria con código nuevo
echo "Recargando PHP-FPM (OPcache)..."
docker exec -i "$APP_CONTAINER" sh -c "kill -USR2 \$(pgrep php-fpm | head -1) 2>/dev/null || true"

echo "Señalizando queue worker para reinicio limpio..."
docker exec -i "$APP_CONTAINER" php artisan queue:restart
sleep 8

# 9. Permisos de almacenamiento
echo "Ajustando permisos de archivos..."
docker exec -i "$APP_CONTAINER" chown -R 1337:1337 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -i "$APP_CONTAINER" chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 10. Aplicar default.conf (nginx -t + reload; fallback a restart)
echo "Aplicando configuración de Nginx..."
"${SCRIPT_DIR}/reload-nginx.sh"

echo "--- Proceso de Actualización (con Nginx) Finalizado ---"
