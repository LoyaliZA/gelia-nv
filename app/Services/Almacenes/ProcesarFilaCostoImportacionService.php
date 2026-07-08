<?php

namespace App\Services\Almacenes;

use App\Models\Producto;
use App\Models\ProductoCosto;

class ProcesarFilaCostoImportacionService
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
            throw new \RuntimeException("Producto con SKU {$sku} no encontrado.");
        }

        $costo = isset($mapping['costo'], $row[$mapping['costo']]) && $row[$mapping['costo']] !== ''
            ? (float) $row[$mapping['costo']]
            : null;
        $costoReposicion = isset($mapping['costo_reposicion'], $row[$mapping['costo_reposicion']]) && $row[$mapping['costo_reposicion']] !== ''
            ? (float) $row[$mapping['costo_reposicion']]
            : null;
        $precioVenta = isset($mapping['precio_venta'], $row[$mapping['precio_venta']]) && $row[$mapping['precio_venta']] !== ''
            ? (float) $row[$mapping['precio_venta']]
            : null;

        if ($costo === null && $costoReposicion === null && $precioVenta === null) {
            throw new \RuntimeException('Debe indicar al menos costo, costo de reposición o precio de venta.');
        }

        $existente = ProductoCosto::where('producto_id', $producto->id)
            ->where('almacen_id', $almacenId)
            ->first();

        $datos = [];
        if ($costo !== null) {
            $datos['costo'] = $costo;
        }
        if ($costoReposicion !== null) {
            $datos['costo_reposicion'] = $costoReposicion;
        }
        if ($precioVenta !== null) {
            $datos['precio_venta'] = $precioVenta;
        }

        if ($existente) {
            $existente->update($datos);

            return ['accion' => 'actualizado'];
        }

        ProductoCosto::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenId,
            'costo' => $costo ?? 0,
            'costo_reposicion' => $costoReposicion,
            'precio_venta' => $precioVenta,
        ]);

        return ['accion' => 'importado'];
    }
}
