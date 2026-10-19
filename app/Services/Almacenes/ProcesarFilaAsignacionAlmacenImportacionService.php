<?php

namespace App\Services\Almacenes;

use App\Models\Producto;
use App\Models\ProductoAlmacen;

class ProcesarFilaAsignacionAlmacenImportacionService
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

        $ubicacion = null;
        if (! empty($mapping['ubicacion']) && isset($row[$mapping['ubicacion']]) && trim((string) $row[$mapping['ubicacion']]) !== '') {
            $ubicacion = trim((string) $row[$mapping['ubicacion']]);
        }

        $existente = ProductoAlmacen::query()
            ->where('producto_id', $producto->id)
            ->where('almacen_id', $almacenId)
            ->first();

        if ($existente) {
            if ($ubicacion !== null && $existente->ubicacion !== $ubicacion) {
                $existente->update(['ubicacion' => $ubicacion]);

                return ['accion' => 'actualizado', 'producto' => $producto];
            }

            return ['accion' => 'sin_cambios', 'producto' => $producto];
        }

        ProductoAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenId,
            'ubicacion' => $ubicacion,
            'activo_en_almacen' => true,
            'origen_asignacion' => 'importacion',
        ]);

        return ['accion' => 'importado', 'producto' => $producto];
    }
}
