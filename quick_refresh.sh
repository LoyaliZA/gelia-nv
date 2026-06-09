
# ==============================================================================
# Script de Actualización Automática - GeliaNV
# ==============================================================================

# 1. Obtener cambios de GitHub
echo "Descargando actualizaciones desde el repositorio..."
git pull origin main

# 2. Sincronizar permisos y excepciones de Git (Preventivo)
docker exec -i gelianv_app git config --global --add safe.directory /var/www/html

# 3. Limpiar caché ANTES de todo (evita servir rutas viejas durante el deploy)
echo "Limpiando caché anterior..."
docker exec -i gelianv_app php artisan optimize:clear
n

# 5. Ejecutar migraciones de base de datos
echo "Sincronizando estructura de base de datos..."
docker exec -i gelianv_app php artisan migrate --force

# 6. Reconstruir assets del frontend (Vite)
echo "Compilando nueva versión del frontend..."
docker exec -i gelianv_app npm install
docker exec -i gelianv_app npm run build

# ==============================================================================
# 7. Regenerar caché con código ya actualizado
# ==============================================================================
echo "Regenerando caché de rutas y optimización..."
docker exec -i gelianv_app php artisan route:cache
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


# ==============================================================================
# 9. Refrescar permisos de almacenamiento
# ==============================================================================
echo "Ajustando permisos de archivos..."
docker exec -i gelianv_app chown -R 1337:1337 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -i gelianv_app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "--- Proceso de Actualización Finalizado ---"