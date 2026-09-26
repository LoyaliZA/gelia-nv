<?php

namespace App\Services\Almacenes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarArchivoVistaPreviaImportacionService
{
    public function guardar(int $userId, UploadedFile $archivo): string
    {
        $extension = $archivo->getClientOriginalExtension() ?: 'csv';
        $nombre = Str::uuid()->toString().'.'.$extension;
        $directorio = "importaciones/{$userId}";

        Storage::makeDirectory($directorio);

        return $archivo->storeAs($directorio, $nombre);
    }

    public function assertPerteneceAlUsuario(string $ruta, int $userId): void
    {
        $prefijo = "importaciones/{$userId}/";

        if (! str_starts_with($ruta, $prefijo)) {
            throw ValidationException::withMessages([
                'file_path' => 'El archivo no pertenece a tu sesión de importación. Vuelve a subirlo.',
            ]);
        }

        if (! Storage::exists($ruta)) {
            throw ValidationException::withMessages([
                'file_path' => 'Archivo temporal no encontrado. Vuelve a subir el archivo.',
            ]);
        }
    }
}
