<?php

namespace App\Services\Almacenes;

use App\Support\Almacenes\OperacionesImportacionAlmacen;

class ProcesarFilaImportacionHubService
{
    public function __construct(
        private readonly ProcesarFilaProductoImportacionService $fichaProducto,
        private readonly ProcesarFilaCostoImportacionService $costos,
        private readonly ProcesarFilaCantidadReferenciaImportacionService $cantidades,
        private readonly ProcesarFilaAsignacionAlmacenImportacionService $asignacion,
    ) {}

    /**
     * @param  list<string>  $operaciones
     * @return array{
     *   productos_creados: int,
     *   productos_actualizados: int,
     *   asignaciones_creadas: int,
     *   costos_creados: int,
     *   costos_actualizados: int,
     *   cantidades_actualizadas: int,
     *   sin_cambios: int,
     *   accion: string
     * }
     */
    public function ejecutar(array $row, array $mapping, ?int $almacenId, array $operaciones): array
    {
        $stats = [
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'asignaciones_creadas' => 0,
            'costos_creados' => 0,
            'costos_actualizados' => 0,
            'cantidades_actualizadas' => 0,
            'sin_cambios' => 0,
        ];

        $hubioCambio = false;

        if (in_array(OperacionesImportacionAlmacen::FICHA_PRODUCTO, $operaciones, true)) {
            $r = $this->fichaProducto->ejecutar($row, $mapping);
            if ($r['accion'] === 'importado') {
                $stats['productos_creados']++;
                $hubioCambio = true;
            } elseif ($r['accion'] === 'actualizado') {
                $stats['productos_actualizados']++;
                $hubioCambio = true;
            }
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::ASIGNACION_ALMACEN, $operaciones, true)) {
            $r = $this->asignacion->ejecutar($row, $mapping, $almacenId);
            if ($r['accion'] === 'importado') {
                $stats['asignaciones_creadas']++;
                $hubioCambio = true;
            }
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::COSTOS_PRECIOS, $operaciones, true)
            && $this->filaTieneDatosDeCosto($row, $mapping)) {
            $r = $this->costos->ejecutar($row, $mapping, $almacenId);
            if ($r['accion'] === 'importado') {
                $stats['costos_creados']++;
                $hubioCambio = true;
            } elseif ($r['accion'] === 'actualizado') {
                $stats['costos_actualizados']++;
                $hubioCambio = true;
            }
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA, $operaciones, true)
            && ! empty($mapping['existencia'])) {
            $r = $this->cantidades->ejecutar($row, $mapping, $almacenId);
            if ($r['accion'] === 'importado' || $r['accion'] === 'actualizado') {
                $stats['cantidades_actualizadas']++;
                $hubioCambio = true;
            } elseif ($r['accion'] === 'sin_cambios') {
                $stats['sin_cambios']++;
            }
        }

        if (! $hubioCambio && $stats['sin_cambios'] === 0) {
            $stats['sin_cambios'] = 1;
        }

        return array_merge($stats, [
            'accion' => $hubioCambio ? 'actualizado' : 'omitido',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function filaTieneDatosDeCosto(array $row, array $mapping): bool
    {
        foreach (['costo', 'costo_reposicion', 'precio_venta'] as $campo) {
            if (! empty($mapping[$campo]) && isset($row[$mapping[$campo]]) && $row[$mapping[$campo]] !== '') {
                return true;
            }
        }

        return false;
    }
}
