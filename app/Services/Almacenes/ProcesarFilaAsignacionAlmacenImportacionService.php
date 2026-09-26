<?php

namespace App\Services\Almacenes;

use App\Models\Producto;
use App\Models\ProductoAlmacen;

class ProcesarFilaAsignacionAlmacenImportacionService
{
    /**
     * @return array{accion: string}
     */
    public function ejecutar(array $row, array $mapping, int $almacenId): array
    {
        $sku = trim((string) ($row[$mapping['sku']] ?? ''));
        if ($sku === '') {
            throw new \RuntimeException('SKU obligatorio.');
        }

        $sku = Producto::normalizarSku($sku);
        $producto = Producto::where('sku', $sku)->first();
        if (! $producto) {
            throw new \RuntimeException("Producto con SKU {$sku} no encontrado. Importa la ficha primero o incluye la operación de productos.");
        }

        $ubicacion = null;
        if (! empty($mapping['ubicacion']) && isset($row[$mapping['ubicacion']]) && $row[$mapping['ubicacion']] !== '') {
            $ubicacion = trim((string) $row[$mapping['ubicacion']]);
        }

        $existente = ProductoAlmacen::query()
            ->where('producto_id', $producto->id)
            ->where('almacen_id', $almacenId)
            ->first();

        if ($existente) {
            if ($ubicacion !== null && $existente->ubicacion !== $ubicacion) {
                $existente->update(['ubicacion' => $ubicacion]);

                return ['accion' => 'actualizado'];
            }

            return ['accion' => 'sin_cambios'];
        }

        ProductoAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenId,
            'ubicacion' => $ubicacion,
            'activo_en_almacen' => true,
            'origen_asignacion' => 'importacion',
        ]);

        return ['accion' => 'importado'];
    }
}
