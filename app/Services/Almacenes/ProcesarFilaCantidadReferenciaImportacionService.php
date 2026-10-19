<?php

namespace App\Services\Almacenes;

use App\Models\Inventario;
use App\Models\Producto;

class ProcesarFilaCantidadReferenciaImportacionService
{
    public function __construct(
        private readonly ResolverProductoPorFolioImportacionService $folios,
    ) {}

    /**
     * @return array{accion: string, producto: Producto}
     */
    public function ejecutar(array $row, array $mapping, int $almacenId, ?Producto $producto = null): array
    {
        if (empty($mapping['existencia'])) {
            throw new \RuntimeException('Columna de existencia no mapeada.');
        }

        if (! isset($row[$mapping['existencia']]) || trim((string) $row[$mapping['existencia']]) === '') {
            throw new \RuntimeException('Existencia obligatoria para cantidades de referencia.');
        }

        $producto ??= $this->folios->exigirExistente($row, $mapping);
        $existencia = (float) $row[$mapping['existencia']];

        $inventario = Inventario::query()
            ->where('producto_id', $producto->id)
            ->where('almacen_id', $almacenId)
            ->first();

        if ($inventario) {
            if ((float) $inventario->existencia === $existencia) {
                return ['accion' => 'sin_cambios', 'producto' => $producto];
            }

            $inventario->update(['existencia' => $existencia]);

            return ['accion' => 'actualizado', 'producto' => $producto];
        }

        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenId,
            'existencia' => $existencia,
        ]);

        return ['accion' => 'importado', 'producto' => $producto];
    }
}
