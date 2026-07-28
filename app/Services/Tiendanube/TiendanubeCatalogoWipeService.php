<?php

namespace App\Services\Tiendanube;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TiendanubeCatalogoWipeService
{
    /**
     * Borra el catálogo espejo local. Nunca toca tiendanube_configuracion (credenciales).
     *
     * @return array<string, int> filas eliminadas por tabla
     */
    public function wipe(): array
    {
        $counts = [];

        return DB::transaction(function () use (&$counts) {
            // Orden: hijos → padres. Credenciales fuera.
            $counts['tiendanube_image_import_items'] = $this->vaciar('tiendanube_image_import_items');
            $counts['tiendanube_image_imports'] = $this->vaciar('tiendanube_image_imports');
            $counts['tiendanube_webhook_deliveries'] = $this->vaciar('tiendanube_webhook_deliveries');
            $counts['tiendanube_producto_categoria'] = $this->vaciar('tiendanube_producto_categoria');
            $counts['tiendanube_producto_variantes'] = $this->vaciar('tiendanube_producto_variantes');
            $counts['tiendanube_producto_imagenes'] = $this->vaciar('tiendanube_producto_imagenes');
            $counts['tiendanube_productos'] = $this->vaciar('tiendanube_productos');
            $counts['tiendanube_categorias'] = $this->vaciar('tiendanube_categorias');
            $counts['tiendanube_sync_logs'] = $this->vaciar('tiendanube_sync_logs');

            return $counts;
        });
    }

    private function vaciar(string $tabla): int
    {
        if (! Schema::hasTable($tabla)) {
            return 0;
        }

        return (int) DB::table($tabla)->delete();
    }
}
