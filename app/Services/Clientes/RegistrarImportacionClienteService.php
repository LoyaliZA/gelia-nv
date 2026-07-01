<?php

namespace App\Services\Clientes;

use App\Models\ImportacionCliente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegistrarImportacionClienteService
{
    public function iniciar(UploadedFile $archivo): ImportacionCliente
    {
        $nombreOriginal = $archivo->getClientOriginalName();
        $slug = Str::slug(pathinfo($nombreOriginal, PATHINFO_FILENAME)) ?: 'importacion';
        $directorio = 'importaciones_clientes/' . now()->format('Y/m');
        $nombreAlmacenado = Str::uuid() . '_' . $slug . '.csv';

        $ruta = $archivo->storeAs($directorio, $nombreAlmacenado, 'local');

        return ImportacionCliente::create([
            'usuario_id' => Auth::id(),
            'nombre_archivo_original' => $nombreOriginal,
            'ruta_almacenamiento' => $ruta,
        ]);
    }

    public function finalizar(ImportacionCliente $importacion, array $stats): ImportacionCliente
    {
        $importacion->update([
            'filas_leidas' => $stats['leidas'] ?? 0,
            'filas_procesadas' => $stats['procesadas'] ?? 0,
            'filas_omitidas' => $stats['omitidas'] ?? 0,
            'errores' => $stats['errores'] ?? 0,
            'ascensos' => $stats['ascensos'] ?? 0,
            'descensos' => $stats['descensos'] ?? 0,
            'clientes_marcados_inactivos' => $stats['clientes_marcados_inactivos'] ?? 0,
            'duracion_seg' => $stats['duracion_seg'] ?? null,
        ]);

        return $importacion->fresh();
    }

    public function rutaAbsoluta(ImportacionCliente $importacion): string
    {
        return Storage::disk('local')->path($importacion->ruta_almacenamiento);
    }
}
