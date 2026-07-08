<?php

namespace App\Services\Almacenes;

use App\Models\Almacenes\ImportacionAlmacenError;
use App\Models\Almacenes\ImportacionAlmacenLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FinalizarImportacionAlmacenAsyncService
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function ejecutar(ImportacionAlmacenLog $log): void
    {
        $token = $this->generarCsvErrores($log);

        $log->update([
            'estado' => 'completado',
            'reporte_errores_token' => $token,
        ]);

        $resumen = array_merge($log->fresh()->resumenParaUi(), [
            'importados' => $log->importados,
            'actualizados' => $log->actualizados,
            'omitidos' => $log->omitidos,
        ]);

        $this->auditoria->importacion($log->tipo, $resumen);

        $this->limpiarArchivos($log);
    }

    private function generarCsvErrores(ImportacionAlmacenLog $log): ?string
    {
        $errores = $log->errores()->orderBy('fila')->get();
        if ($errores->isEmpty()) {
            return null;
        }

        $token = Str::uuid()->toString();
        $relativePath = "temp/import_errores_{$token}.csv";
        $fullPath = Storage::path($relativePath);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $out = fopen($fullPath, 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['fila', 'referencia', 'campo', 'mensaje']);

        foreach ($errores as $error) {
            fputcsv($out, [$error->fila, $error->referencia, $error->campo, $error->mensaje]);
        }

        fclose($out);

        return $token;
    }

    private function limpiarArchivos(ImportacionAlmacenLog $log): void
    {
        if ($log->archivo_ruta && Storage::exists($log->archivo_ruta)) {
            Storage::delete($log->archivo_ruta);
        }

        if ($log->archivo_normalizado && Storage::exists($log->archivo_normalizado)) {
            Storage::delete($log->archivo_normalizado);
        }

        $directorio = dirname($log->archivo_ruta);
        if ($directorio && Storage::exists($directorio)) {
            $restantes = Storage::files($directorio);
            if (empty($restantes)) {
                Storage::deleteDirectory($directorio);
            }
        }
    }

    public function registrarError(ImportacionAlmacenLog $log, int $fila, string $referencia, string $campo, string $mensaje): void
    {
        ImportacionAlmacenError::create([
            'importacion_almacen_log_id' => $log->id,
            'fila' => $fila,
            'referencia' => $referencia ?: '—',
            'campo' => $campo,
            'mensaje' => $mensaje,
        ]);
    }
}
