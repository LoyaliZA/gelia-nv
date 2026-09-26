<?php

namespace App\Services\Almacenes;

use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Support\Almacenes\OperacionesImportacionAlmacen;
use Illuminate\Support\Facades\DB;

class SimularImportacionAlmacenService
{
    public function __construct(
        private readonly LeerFilasImportacionAlmacenService $lector,
        private readonly ProcesarFilaImportacionHubService $hub,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(ImportacionAlmacenLog $log): array
    {
        $operaciones = $log->operaciones ?? [];
        if ($operaciones === []) {
            $operaciones = $this->operacionesDesdeTipoLegacy($log->tipo);
        }

        $mapping = $log->mapping ?? [];
        $almacenId = $log->almacen_id ? (int) $log->almacen_id : null;

        if (! $log->archivo_normalizado) {
            $normalizado = $this->lector->normalizarArchivo($log);
            $log->update([
                'archivo_normalizado' => $normalizado['path'],
                'total_filas' => $normalizado['total_filas'],
            ]);
            $log = $log->fresh();
        }

        $resumen = [
            'total_filas' => 0,
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'asignaciones_creadas' => 0,
            'costos_creados' => 0,
            'costos_actualizados' => 0,
            'cantidades_actualizadas' => 0,
            'sin_cambios' => 0,
            'errores' => 0,
            'detalle_errores' => [],
            'skus_duplicados' => [],
        ];

        $skusVistos = [];
        $offset = 0;
        $tamano = 200;

        while (true) {
            $lote = $this->lector->leerLote($log, $offset, $tamano);
            if ($lote['filas'] === []) {
                break;
            }

            foreach ($lote['filas'] as $item) {
                $resumen['total_filas']++;
                $numeroFila = $item['numero_fila'];
                $row = $item['row'];
                $sku = trim((string) ($row[$mapping['sku'] ?? ''] ?? ''));
                if ($sku !== '') {
                    $clave = mb_strtolower($sku);
                    if (isset($skusVistos[$clave])) {
                        $resumen['skus_duplicados'][] = [
                            'sku' => $sku,
                            'fila' => $numeroFila,
                            'primera_fila' => $skusVistos[$clave],
                        ];
                    } else {
                        $skusVistos[$clave] = $numeroFila;
                    }
                }

                try {
                    DB::beginTransaction();
                    $stats = $this->hub->ejecutar($row, $mapping, $almacenId, $operaciones);
                    DB::rollBack();

                    foreach (['productos_creados', 'productos_actualizados', 'asignaciones_creadas', 'costos_creados', 'costos_actualizados', 'cantidades_actualizadas', 'sin_cambios'] as $k) {
                        $resumen[$k] += (int) ($stats[$k] ?? 0);
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $resumen['errores']++;
                    if (count($resumen['detalle_errores']) < 100) {
                        $resumen['detalle_errores'][] = [
                            'fila' => $numeroFila,
                            'sku' => $sku ?: '—',
                            'mensaje' => $e->getMessage(),
                        ];
                    }
                }
            }

            $offset += count($lote['filas']);
            if ($offset >= ($log->total_filas ?: $offset)) {
                break;
            }
        }

        $log->update([
            'estado' => 'simulado',
            'resumen_simulacion' => $resumen,
        ]);

        return $resumen;
    }

    /**
     * @return list<string>
     */
    private function operacionesDesdeTipoLegacy(string $tipo): array
    {
        return match ($tipo) {
            'productos' => [OperacionesImportacionAlmacen::FICHA_PRODUCTO],
            'costos' => [OperacionesImportacionAlmacen::COSTOS_PRECIOS],
            'inventarios' => [
                OperacionesImportacionAlmacen::FICHA_PRODUCTO,
                OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA,
                OperacionesImportacionAlmacen::COSTOS_PRECIOS,
            ],
            default => [],
        };
    }
}
