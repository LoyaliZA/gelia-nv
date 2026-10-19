<?php

namespace App\Services\Almacenes;

use App\Models\Producto;
use App\Models\ProductoCosto;

class ProcesarFilaCostoImportacionService
{
    public function __construct(
        private readonly ResolverProductoPorFolioImportacionService $folios,
    ) {}

    /**
     * @return array{accion: string, producto: Producto}
     */
    public function ejecutar(array $row, array $mapping, int $almacenId, ?Producto $producto = null): array
    {
        $producto ??= $this->folios->exigirExistente($row, $mapping);

        $costo = $this->monto($row, $mapping, 'costo');
        $costoReposicion = $this->monto($row, $mapping, 'costo_reposicion');
        $precioVenta = $this->monto($row, $mapping, 'precio_venta');

        if ($costo === null && $costoReposicion === null && $precioVenta === null) {
            throw new \RuntimeException('Debe indicar al menos costo, costo de reposición o precio de venta.');
        }

        $existente = ProductoCosto::query()
            ->where('producto_id', $producto->id)
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

            return ['accion' => 'actualizado', 'producto' => $producto];
        }

        ProductoCosto::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenId,
            'costo' => $costo ?? 0,
            'costo_reposicion' => $costoReposicion,
            'precio_venta' => $precioVenta,
        ]);

        return ['accion' => 'importado', 'producto' => $producto];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function monto(array $row, array $mapping, string $campo): ?float
    {
        if (empty($mapping[$campo]) || ! array_key_exists($mapping[$campo], $row)) {
            return null;
        }

        $valor = trim((string) $row[$mapping[$campo]]);
        if ($valor === '') {
            return null;
        }

        return (float) $valor;
    }
}
