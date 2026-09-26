<?php

namespace App\Jobs\Almacenes;

use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Services\Almacenes\FinalizarImportacionAlmacenAsyncService;
use App\Services\Almacenes\LeerFilasImportacionAlmacenService;
use App\Services\Almacenes\ProcesarFilaCostoImportacionService;
use App\Services\Almacenes\ProcesarFilaImportacionHubService;
use App\Services\Almacenes\ProcesarFilaInventarioImportacionService;
use App\Services\Almacenes\ProcesarFilaProductoImportacionService;
use App\Services\Almacenes\ProcesarFilaVentaImportacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ImportarAlmacenCatalogoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    private const BATCH_SIZE = 100;

    public function __construct(
        public int $logId,
        public int $offset = 0,
    ) {}

    public function handle(
        LeerFilasImportacionAlmacenService $lector,
        ProcesarFilaProductoImportacionService $procesadorProducto,
        ProcesarFilaInventarioImportacionService $procesadorInventario,
        ProcesarFilaCostoImportacionService $procesadorCosto,
        ProcesarFilaImportacionHubService $procesadorHub,
        FinalizarImportacionAlmacenAsyncService $finalizador,
    ): void {
        $log = ImportacionAlmacenLog::find($this->logId);
        if (! $log || $log->estado === 'cancelado') {
            return;
        }

        if ($log->estado === 'pendiente') {
            $log->update(['estado' => 'en_proceso']);
        }

        try {
            if (! $log->archivo_normalizado || ! \Illuminate\Support\Facades\Storage::exists($log->archivo_normalizado)) {
                $normalizado = $lector->normalizarArchivo($log);
                $log->update([
                    'archivo_normalizado' => $normalizado['path'],
                    'total_filas' => $normalizado['total_filas'],
                ]);
                $log = $log->fresh();
            }

            if ($log->fresh()->estado === 'cancelado') {
                return;
            }

            if ($log->total_filas === 0) {
                $finalizador->ejecutar($log);

                return;
            }

            if ($this->offset >= $log->total_filas) {
                $finalizador->ejecutar($log);

                return;
            }

            $lote = $lector->leerLote($log, $this->offset, self::BATCH_SIZE);
            $mapping = $log->mapping ?? [];
            $stats = [
                'importados' => 0,
                'actualizados' => 0,
                'omitidos' => 0,
                'productos_creados' => 0,
                'productos_actualizados' => 0,
                'asignaciones_creadas' => 0,
                'costos_creados' => 0,
                'costos_actualizados' => 0,
                'cantidades_actualizadas' => 0,
                'sin_cambios' => 0,
            ];

            foreach ($lote['filas'] as $item) {
                if ($log->fresh()->estado === 'cancelado') {
                    return;
                }

                $numeroFila = $item['numero_fila'];
                $row = $item['row'];
                $referencia = trim((string) ($row[$mapping['sku']] ?? ''));

                try {
                    $resultado = DB::transaction(fn () => $this->procesarFila(
                        $log,
                        $row,
                        $mapping,
                        $procesadorProducto,
                        $procesadorInventario,
                        $procesadorCosto,
                        $procesadorHub,
                    ));

                    if ($log->tipo === 'hub') {
                        foreach (['productos_creados', 'productos_actualizados', 'asignaciones_creadas', 'costos_creados', 'costos_actualizados', 'cantidades_actualizadas', 'sin_cambios'] as $k) {
                            $stats[$k] += (int) ($resultado[$k] ?? 0);
                        }
                        if (($resultado['accion'] ?? '') === 'omitido') {
                            $stats['omitidos']++;
                        } else {
                            $stats['actualizados']++;
                        }
                    } else {
                        $clave = $resultado['accion'] === 'importado' ? 'importados' : 'actualizados';
                        $stats[$clave]++;
                    }
                } catch (Throwable $e) {
                    $stats['omitidos']++;
                    $finalizador->registrarError($log, $numeroFila, $referencia ?: '—', 'general', $e->getMessage());
                }
            }

            $procesadosEnLote = count($lote['filas']);
            $log->increment('procesados', $procesadosEnLote);
            $log->increment('importados', $stats['importados']);
            $log->increment('actualizados', $stats['actualizados']);
            $log->increment('omitidos', $stats['omitidos']);
            foreach (['productos_creados', 'productos_actualizados', 'asignaciones_creadas', 'costos_creados', 'costos_actualizados', 'cantidades_actualizadas', 'sin_cambios'] as $k) {
                if ($stats[$k] > 0) {
                    $log->increment($k, $stats[$k]);
                }
            }

            $nuevoOffset = $this->offset + $procesadosEnLote;
            $log = $log->fresh();

            if ($nuevoOffset < $log->total_filas) {
                self::dispatch($log->id, $nuevoOffset);

                return;
            }

            $finalizador->ejecutar($log);
        } catch (Throwable $e) {
            $log?->update([
                'estado' => 'error',
                'mensaje_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{accion: string}
     */
    private function procesarFila(
        ImportacionAlmacenLog $log,
        array $row,
        array $mapping,
        ProcesarFilaProductoImportacionService $procesadorProducto,
        ProcesarFilaInventarioImportacionService $procesadorInventario,
        ProcesarFilaCostoImportacionService $procesadorCosto,
        ProcesarFilaImportacionHubService $procesadorHub,
    ): array {
        return match ($log->tipo) {
            'hub' => $procesadorHub->ejecutar(
                $row,
                $mapping,
                $log->almacen_id ? (int) $log->almacen_id : null,
                $log->operaciones ?? [],
            ),
            'productos' => $procesadorProducto->ejecutar($row, $mapping),
            'inventarios' => $procesadorInventario->ejecutar($row, $mapping, (int) $log->almacen_id),
            'costos' => $procesadorCosto->ejecutar($row, $mapping, (int) $log->almacen_id),
            'ventas' => $this->mapearResultadoVenta(
                app(ProcesarFilaVentaImportacionService::class)->ejecutar(
                    $row,
                    $mapping,
                    $log->almacen_id ? (int) $log->almacen_id : null
                )
            ),
            default => throw new \RuntimeException('Tipo de importación no soportado.'),
        };
    }

    /** @param  array{ok: bool, error?: string}  $r */
    private function mapearResultadoVenta(array $r): array
    {
        if (! ($r['ok'] ?? false)) {
            throw new \RuntimeException($r['error'] ?? 'Error en fila de ventas');
        }

        return ['accion' => 'actualizado'];
    }

    public function failed(?Throwable $exception): void
    {
        $log = ImportacionAlmacenLog::find($this->logId);
        if (! $log || $log->estado === 'cancelado') {
            return;
        }

        $log->update([
            'estado' => 'interrumpido',
            'mensaje_error' => $exception?->getMessage() ?? 'El proceso de importación falló.',
        ]);
    }
}
