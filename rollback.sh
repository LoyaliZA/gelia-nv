#!/bin/bash
set -e

# ==============================================================================
# Script de Rollback - GeliaNV
# ==============================================================================

COMMIT_ACTUAL=$(git rev-parse HEAD)
COMMIT_ANTERIOR=$(git rev-parse HEAD~1)

echo "⚠️  ROLLBACK DE CÓDIGO"
echo ""
echo "   Commit actual:   $COMMIT_ACTUAL"
echo "   Volviendo a:     $COMMIT_ANTERIOR"
echo ""
read -p "¿Confirmas el rollback? (s/n): " CONFIRMAR

if [ "$CONFIRMAR" != "s" ]; then
    echo "Rollback cancelado."
    exit 0
fi

# 1. Revertir al commit anterior
git checkout $COMMIT_ANTERIOR

# 2. Limpiar caché
echo "Limpiando caché..."
docker exec -i gelianv_app php artisan optimize:clear

# 3. Restaurar dependencias
echo "Restaurando dependencias..."
docker exec -i gelianv_app composer install --no-dev --optimize-autoloader --no-interaction

# 4. Recompilar frontend
echo "Recompilando frontend..."
docker exec -i gelianv_app npm install
docker exec -i gelianv_app npm run build

# 5. Regenerar caché
echo "Regenerando caché..."
docker exec -i gelianv_app php artisan route:cache
docker exec -i gelianv_app php artisan optimize

# 6. Recargar PHP-FPM
echo "Recargando PHP-FPM..."
docker exec -i gelianv_app sh -c "kill -USR2 \$(pgrep php-fpm | head -1) 2>/dev/null || true"

# 7. Reiniciar queue worker
echo "Reiniciando queue worker..."
docker exec -i gelianv_app php artisan queue:restart
sleep 8

# 8. Permisos
echo "Ajustando permisos..."
docker exec -i gelianv_app chown -R 1337:1337 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec -i gelianv_app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo ""
echo "✅ Rollback completado."
echo "⚠️  La base de datos NO fue revertida."