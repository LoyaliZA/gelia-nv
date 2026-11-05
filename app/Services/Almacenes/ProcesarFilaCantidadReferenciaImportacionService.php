<?php

namespace App\Services\Almacenes;

use App\Models\Inventario;
use App\Models\Producto;

class ProcesarFilaCantidadReferenciaImportacionService
{
    public function __construct(
        private readonly ResolverProductoPorFolioImportacionService $folios,
        private readonly ProcesarFilaAsignacionAlmacenImportacionService $asignacion,
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
        $this->asignacion->ejecutar($row, $mapping, $almacenId, $producto);
        $existencia = (float) $row[$mapping['existencia']];
        $ubicacion = $this->ubicacion($row, $mapping);

        $inventario = Inventario::query()
            ->where('producto_id', $producto->id)
            ->where('almacen_id', $almacenId)
            ->first();

        if ($inventario) {
            $datos = [];
            if ((float) $inventario->existencia !== $existencia) {
                $datos['existencia'] = $existencia;
            }
            if ($ubicacion !== null && $inventario->ubicacion !== $ubicacion) {
                $datos['ubicacion'] = $ubicacion;
            }
            if ($datos === []) {
                return ['accion' => 'sin_cambios', 'producto' => $producto];
            }

            $inventario->update($datos);

            return ['accion' => 'actualizado', 'producto' => $producto];
        }

        Inventario::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacenId,
            'existencia' => $existencia,
            'ubicacion' => $ubicacion,
        ]);

        return ['accion' => 'importado', 'producto' => $producto];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function ubicacion(array $row, array $mapping): ?string
    {
        if (empty($mapping['ubicacion']) || ! isset($row[$mapping['ubicacion']])) {
            return null;
        }

        $valor = trim((string) $row[$mapping['ubicacion']]);

        return $valor === '' ? null : $valor;
    }
}
