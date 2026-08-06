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

        $resultado = $this->procesadorProducto->ejecutar($row, $mapping);
        $producto = $resultado['producto'];

        Inventario::updateOrCreate(
            ['producto_id' => $producto->id, 'almacen_id' => $almacenId],
            ['existencia' => $existencia]
        );

        $this->sincronizarCostoSiAplica($row, $mapping, $producto->id, $almacenId);

        $producto->update(['activo' => true]);

        return ['accion' => $resultado['accion']];
    }

    /**
     * Misma fuente que Almacenes→Costos: producto_costos por (producto, almacén).
     * Antes solo corría si mapping.costo estaba set; precio_venta solo no creaba fila.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function sincronizarCostoSiAplica(array $row, array $mapping, int $productoId, int $almacenId): void
    {
        $tieneCosto = ! empty($mapping['costo']) && isset($row[$mapping['costo']]) && $row[$mapping['costo']] !== '';
        $tieneReposicion = ! empty($mapping['costo_reposicion']) && isset($row[$mapping['costo_reposicion']]) && $row[$mapping['costo_reposicion']] !== '';
        $tienePrecio = ! empty($mapping['precio_venta']) && isset($row[$mapping['precio_venta']]) && $row[$mapping['precio_venta']] !== '';

        if (! $tieneCosto && ! $tieneReposicion && ! $tienePrecio) {
            return;
        }

        $datos = [];
        if ($tieneCosto) {
            $datos['costo'] = (float) $row[$mapping['costo']];
        }
        if ($tieneReposicion) {
            $datos['costo_reposicion'] = (float) $row[$mapping['costo_reposicion']];
        }
        if ($tienePrecio) {
            $datos['precio_venta'] = (float) $row[$mapping['precio_venta']];
        }

        $existente = ProductoCosto::query()
            ->where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->first();

        if ($existente) {
            $existente->update($datos);

            return;
        }

        ProductoCosto::create([
            'producto_id' => $productoId,
            'almacen_id' => $almacenId,
            'costo' => $datos['costo'] ?? 0,
            'costo_reposicion' => $datos['costo_reposicion'] ?? null,
            'precio_venta' => $datos['precio_venta'] ?? null,
        ]);
    }
}
