#!/bin/bash

# ==============================================================================
# Script de Actualización Automática - GeliaNV
# ==============================================================================

# 1. Obtener cambios de GitHub
echo "Descargando actualizaciones desde el repositorio..."
git pull origin main

# 2. Sincronizar permisos y excepciones de Git (Preventivo)
docker exec -it gelianv_app git config --global --add safe.directory /var/www/html

# 3. Actualizar dependencias de PHP (Laravel)
echo "Actualizando dependencias de Composer..."
docker exec -it gelianv_app composer install --no-dev --optimize-autoloader

# 4. Ejecutar migraciones de base de datos
echo "Sincronizando estructura de base de datos..."
docker exec -it gelianv_app php artisan migrate --force

# 5. Reconstruir assets del frontend (Vite)
echo "Compilando nueva versión del frontend..."
docker exec -it gelianv_app npm install
docker exec -it gelianv_app npm run build

# 6. Optimización del sistema
echo "Limpiando y regenerando caché de optimización..."
docker exec -it gelianv_app php artisan optimize:clear
docker exec -it gelianv_app php artisan optimize

# 7. Refrescar permisos de almacenamiento (Seguridad)
echo "Ajustando permisos de archivos..."
docker exec -it gelianv_app chown -R 1337:1337 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -it gelianv_app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "--- Proceso de Actualización Finalizado ---"