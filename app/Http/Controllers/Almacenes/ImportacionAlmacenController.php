<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Jobs\Almacenes\ImportarAlmacenCatalogoJob;
use App\Models\Almacenes\ImportacionAlmacenLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportacionAlmacenController extends Controller
{
    public function descargarReporteErrores(string $token): BinaryFileResponse
    {
        $user = request()->user();

        if (! $user->can('catalogos.gestionar')
            && ! $user->can('almacenes.productos.gestionar')
            && ! $user->can('almacenes.inventarios.importar')
            && ! $user->can('almacenes.costos.importar')) {
            abort(403);
        }

        $path = Storage::path('temp/import_errores_' . preg_replace('/[^a-f0-9\-]/i', '', $token) . '.csv');

        if (! file_exists($path)) {
            abort(404, 'Reporte no encontrado o expirado.');
        }

        return response()->download($path, 'reporte_errores_importacion.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }

    public function progreso(int $id): JsonResponse
    {
        $this->autorizarVerImportacion();

        $log = ImportacionAlmacenLog::findOrFail($id);
        ImportacionAlmacenLog::marcarZombieSiAplica($log);
        $log = $log->fresh();

        return response()->json(array_merge($log->toArray(), [
            'etiqueta_tipo' => $log->etiquetaTipo(),
            'resumen' => $log->resumenParaUi(),
        ]));
    }

    public function activo(): JsonResponse
    {
        $this->autorizarVerImportacion();

        return response()->json(['log' => ImportacionAlmacenLog::activo()]);
    }

    public function cancelar(int $id): JsonResponse
    {
        $this->autorizarGestionarImportacion();

        $log = ImportacionAlmacenLog::findOrFail($id);

        if (! in_array($log->estado, ['pendiente', 'en_proceso'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'La importación no se puede cancelar porque ya finalizó o dio error.',
            ], 422);
        }

        $log->update(['estado' => 'cancelado']);
        Artisan::call('queue:restart');

        return response()->json([
            'success' => true,
            'message' => 'La importación fue cancelada.',
        ]);
    }

    public function continuar(int $id): JsonResponse
    {
        $this->autorizarGestionarImportacion();

        $log = ImportacionAlmacenLog::findOrFail($id);
        ImportacionAlmacenLog::marcarZombieSiAplica($log);
        $log = $log->fresh();

        if (! $log->puedeContinuar() && ! in_array($log->estado, ['interrumpido', 'error', 'en_proceso'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta importación no se puede continuar.',
            ], 422);
        }

        if ($log->procesados >= $log->total_filas) {
            return response()->json([
                'success' => false,
                'message' => 'La importación ya procesó todas las filas.',
            ], 422);
        }

        if (! $log->archivo_normalizado || ! Storage::exists($log->archivo_normalizado)) {
            return response()->json([
                'success' => false,
                'message' => 'No hay archivo normalizado para continuar esta importación.',
            ], 422);
        }

        Artisan::call('queue:restart');

        $offset = (int) $log->procesados;

        $log->update([
            'estado' => 'completado',
            'mensaje_error' => "Cerrado parcialmente en {$offset}/{$log->total_filas}. Continuado en nuevo proceso.",
        ]);

        $nuevoLog = ImportacionAlmacenLog::create([
            'user_id' => $log->user_id,
            'tipo' => $log->tipo,
            'almacen_id' => $log->almacen_id,
            'archivo_ruta' => $log->archivo_ruta,
            'archivo_normalizado' => $log->archivo_normalizado,
            'mapping' => $log->mapping,
            'total_filas' => $log->total_filas,
            'procesados' => $offset,
            'importados' => $log->importados,
            'actualizados' => $log->actualizados,
            'omitidos' => $log->omitidos,
            'estado' => 'pendiente',
            'payload' => ['offset' => $offset, 'continuado_de' => $log->id],
        ]);

        ImportarAlmacenCatalogoJob::dispatch($nuevoLog->id, $offset);

        return response()->json([
            'success' => true,
            'log_id' => $nuevoLog->id,
            'message' => 'Importación reanudada.',
        ]);
    }

    private function autorizarVerImportacion(): void
    {
        $user = request()->user();
        if ($user->can('catalogos.gestionar')
            || $user->can('almacenes.productos.gestionar')
            || $user->can('almacenes.inventarios.importar')
            || $user->can('almacenes.costos.importar')) {
            return;
        }

        abort(403);
    }

    private function autorizarGestionarImportacion(): void
    {
        $user = request()->user();
        if ($user->can('catalogos.gestionar')
            || $user->can('almacenes.productos.gestionar')
            || $user->can('almacenes.inventarios.importar')
            || $user->can('almacenes.costos.importar')) {
            return;
        }

        abort(403);
    }
}
