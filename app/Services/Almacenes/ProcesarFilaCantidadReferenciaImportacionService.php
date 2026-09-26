<?php

namespace App\Services\Almacenes;

use App\Models\Inventario;
use App\Models\Producto;

class ProcesarFilaCantidadReferenciaImportacionService
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

        if (empty($mapping['existencia'])) {
            throw new \RuntimeException('Columna de existencia no mapeada.');
        }

        if (! isset($row[$mapping['existencia']]) || $row[$mapping['existencia']] === '') {
            throw new \RuntimeException('Existencia obligatoria para cantidades de referencia.');
        }

        $sku = Producto::normalizarSku($sku);
        $producto = Producto::where('sku', $sku)->first();
        if (! $producto) {
            throw new \RuntimeException("Producto con SKU {$sku} no encontrado.");
        }

        $existencia = (float) $row[$mapping['existencia']];

        $inventario = Inventario::query()
            ->where('producto_id', $producto->id)
            ->where('almacen_id', $almacenId)
            ->first();

        if ($inventario) {
            if ((float) $inventario->existencia === $existencia) {
                return ['accion' => 'sin_cambios'];
            }

            $inventario->update(['existencia' => $existencia]);

            return ['accion' => 'actualizado'];
        }

        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenId,
            'existencia' => $existencia,
        ]);

        return ['accion' => 'importado'];
    }
}
