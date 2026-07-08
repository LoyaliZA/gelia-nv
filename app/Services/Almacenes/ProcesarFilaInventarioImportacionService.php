<?php

namespace App\Services\Almacenes;

use App\Models\Inventario;
use App\Models\ProductoCosto;

class ProcesarFilaInventarioImportacionService
{
    public function __construct(
        private readonly ProcesarFilaProductoImportacionService $procesadorProducto,
    ) {}

    /**
     * @return array{accion: string}
     */
    public function ejecutar(array $row, array $mapping, int $almacenId): array
    {
        if (! isset($row[$mapping['existencia']]) || $row[$mapping['existencia']] === '') {
            throw new \RuntimeException('Existencia obligatoria.');
        }

        $existencia = (float) $row[$mapping['existencia']];
        $costo = isset($mapping['costo'], $row[$mapping['costo']]) && $row[$mapping['costo']] !== ''
            ? (float) $row[$mapping['costo']]
            : 0;
        $precioVenta = isset($mapping['precio_venta'], $row[$mapping['precio_venta']]) && $row[$mapping['precio_venta']] !== ''
            ? (float) $row[$mapping['precio_venta']]
            : null;
        $costoReposicion = isset($mapping['costo_reposicion'], $row[$mapping['costo_reposicion']]) && $row[$mapping['costo_reposicion']] !== ''
            ? (float) $row[$mapping['costo_reposicion']]
            : null;

        $resultado = $this->procesadorProducto->ejecutar($row, $mapping);
        $producto = $resultado['producto'];

        Inventario::updateOrCreate(
            ['producto_id' => $producto->id, 'almacen_id' => $almacenId],
            ['existencia' => $existencia]
        );

        if (! empty($mapping['costo'])) {
            ProductoCosto::updateOrCreate(
                ['producto_id' => $producto->id, 'almacen_id' => $almacenId],
                [
                    'costo' => $costo,
                    'costo_reposicion' => $costoReposicion,
                    'precio_venta' => $precioVenta,
                ]
            );
        }

        $producto->update(['activo' => true]);

        return ['accion' => $resultado['accion']];
    }
}
