<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubirFotosActivoService
{
    public function __construct(
        private OptimizarImagenActivoService $optimizarImagen,
    ) {}

    public function ejecutar(Activo $activo, array $archivos): void
    {
        $actuales = $activo->fotos()->count();
        $nuevas = count(array_filter($archivos, fn ($f) => $f instanceof UploadedFile));

        if ($actuales + $nuevas > 5) {
            throw ValidationException::withMessages([
                'fotos' => 'Máximo 5 fotografías por activo.',
            ]);
        }

        DB::transaction(function () use ($activo, $archivos, $actuales) {
            $orden = $actuales;

            foreach ($archivos as $archivo) {
                if (!$archivo instanceof UploadedFile || !$archivo->isValid()) {
                    continue;
                }

                $orden++;
                $optimizada = $this->optimizarImagen->ejecutar($archivo, $activo->id);

                ActivoFoto::create([
                    'activo_id' => $activo->id,
                    'ruta' => $optimizada['ruta'],
                    'nombre_original' => $optimizada['nombre_original'],
                    'orden' => $orden,
                    'es_principal' => $actuales === 0 && $orden === 1,
                    'tamano_bytes' => $optimizada['tamano_bytes'],
                ]);
            }
        });
    }
}
