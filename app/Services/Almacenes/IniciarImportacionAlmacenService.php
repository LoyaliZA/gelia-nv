<?php

namespace App\Services\Almacenes;

use App\Jobs\Almacenes\ImportarAlmacenCatalogoJob;
use App\Models\Almacenes\ImportacionAlmacenLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class IniciarImportacionAlmacenService
{
    public function __construct(
        private readonly LeerFilasImportacionAlmacenService $lector,
    ) {}

    /**
     * @return array{log_id: int}
     */
    public function ejecutar(Request $request, string $tipo): array
    {
        $rules = [
            'file_path' => 'required|string',
            'mapping' => 'required|array',
            'mapping.sku' => 'required|string',
        ];

        if ($tipo === 'productos') {
            $rules['mapping.descripcion'] = 'required|string';
        }

        if (in_array($tipo, ['inventarios', 'costos'], true)) {
            $rules['almacen_id'] = 'required|exists:almacenes,id';
            if ($tipo === 'inventarios') {
                $rules['mapping.descripcion'] = 'required|string';
                $rules['mapping.existencia'] = 'required|string';
            }
        }

        $validated = $request->validate($rules);

        $tempPath = $validated['file_path'];
        if (! str_starts_with($tempPath, 'temp/')) {
            throw ValidationException::withMessages([
                'file_path' => 'Ruta de archivo temporal inválida.',
            ]);
        }

        if (! Storage::exists($tempPath)) {
            throw ValidationException::withMessages([
                'file_path' => 'Archivo temporal no encontrado. Vuelve a subir el archivo.',
            ]);
        }

        $log = ImportacionAlmacenLog::create([
            'user_id' => $request->user()->id,
            'tipo' => $tipo,
            'almacen_id' => $validated['almacen_id'] ?? null,
            'archivo_ruta' => '',
            'mapping' => $validated['mapping'],
            'estado' => 'pendiente',
            'payload' => ['offset' => 0],
        ]);

        $extension = pathinfo($tempPath, PATHINFO_EXTENSION) ?: 'csv';
        $destino = "importaciones_almacenes/{$log->id}/source.{$extension}";
        Storage::makeDirectory(dirname($destino));
        Storage::move($tempPath, $destino);

        $log->update(['archivo_ruta' => $destino]);

        ImportarAlmacenCatalogoJob::dispatch($log->id, 0);

        return ['log_id' => $log->id];
    }
}
