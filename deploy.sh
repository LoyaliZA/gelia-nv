#!/bin/bash
# Script de Despliegue Automatizado para Producción - GeliaNV
#./deploy.sh

echo "--- Iniciando Despliegue de GeliaNV ---"

# 1. Compilar imagen base y levantar contenedores
echo "Compilando imagen base de la aplicación (esto puede tomar varios minutos)..."
docker compose -f docker-compose.prod.yaml build app

echo "Levantando la infraestructura de contenedores..."
docker compose -f docker-compose.prod.yaml up -d

# 2. Instalar dependencias del backend (PHP)
echo "Configurando excepciones de seguridad para Git..."
docker exec -it gelianv_app git config --global --add safe.directory /var/www/html

echo "Instalando dependencias de Composer..."
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
echo "Recreando enlace simbólico de almacenamiento..."
# Eliminamos cualquier enlace previo para evitar conflictos
docker exec -it gelianv_app rm -rf public/storage
docker exec -it gelianv_app php artisan storage:link

# Recrear estructura de directorios
docker exec -it gelianv_app mkdir -p /var/www/html/storage/framework/{sessions,views,cache/data}
docker exec -it gelianv_app mkdir -p /var/www/html/storage/app/public

# Aplicar permisos (Aseguramos que Nginx pueda leer)
docker exec -it gelianv_app chown -R 1337:1337 /var/www/html/storage
docker exec -it gelianv_app chmod -R 775 /var/www/html/storage

# 6. Ejecutar migraciones y poblado inicial de base de datos
echo "Construyendo estructura de base de datos..."
backup_db_antes_de_migrar
docker exec -it gelianv_app php artisan migrate --force
docker exec -it gelianv_app php artisan db:seed --force

# 7. Optimización de memoria caché para producción
echo "Optimizando rutas y configuraciones..."
docker exec -it gelianv_app php artisan optimize:clear
docker exec -it gelianv_app php artisan optimize

echo "--- Despliegue Completado Correctamente ---"