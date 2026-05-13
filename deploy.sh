#!/bin/bash
# Script de Despliegue Automatizado para Producción - GeliaNV

echo "--- Iniciando Despliegue de GeliaNV ---"

# 1. Compilar imágenes y levantar contenedores en segundo plano
docker compose -f docker-compose.prod.yaml up -d --build

# 2. Instalar dependencias del backend (PHP)
docker exec -it gelianv_app composer install --no-dev --optimize-autoloader

# 3. Generación condicional de APP_KEY
# Valida si la clave está vacía para no arruinar las sesiones activas
echo "Verificando llave de encriptación..."
docker exec -it gelianv_app bash -c 'grep -q "^APP_KEY=$" .env && php artisan key:generate --force || echo "APP_KEY ya está configurada. Omitiendo generación."'

# 4. Instalar y compilar dependencias del frontend (Vite)
echo "Compilando assets estáticos..."
docker exec -it gelianv_app npm install
docker exec -it gelianv_app npm run build

# 5. Enlaces simbólicos y configuración de permisos
echo "Configurando almacenamiento y permisos..."
docker exec -it gelianv_app php artisan storage:link
docker exec -it gelianv_app chown -R 1337:1337 /var/www/html/storage
docker exec -it gelianv_app chown -R 1337:1337 /var/www/html/bootstrap/cache
docker exec -it gelianv_app chmod -R 775 /var/www/html/storage
docker exec -it gelianv_app chmod -R 775 /var/www/html/bootstrap/cache

# 6. Ejecutar migraciones y poblado inicial de base de datos
echo "Construyendo estructura de base de datos..."
docker exec -it gelianv_app php artisan migrate --force
docker exec -it gelianv_app php artisan db:seed --force

# 7. Optimización de memoria caché para producción
echo "Optimizando rutas y configuraciones..."
docker exec -it gelianv_app php artisan config:cache
docker exec -it gelianv_app php artisan route:cache
docker exec -it gelianv_app php artisan view:cache

echo "--- Despliegue Completado Correctamente ---"