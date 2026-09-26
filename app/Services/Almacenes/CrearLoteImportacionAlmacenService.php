<?php

namespace App\Services\Almacenes;

use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Support\Almacenes\OperacionesImportacionAlmacen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CrearLoteImportacionAlmacenService
{
    public function __construct(
        private readonly GuardarArchivoVistaPreviaImportacionService $vistaPrevia,
        private readonly LeerEncabezadosArchivoImportacionService $encabezados,
        private readonly AlcanceAlmacenesService $alcance,
    ) {}

    /**
     * @param  list<string>  $operaciones
     * @return array{lote: ImportacionAlmacenLog, headers: array<int, string>}
     */
    public function analizar(
        int $userId,
        UploadedFile $archivo,
        array $operaciones,
        ?int $almacenId,
    ): array {
        $operaciones = array_values(array_intersect($operaciones, OperacionesImportacionAlmacen::todas()));
        if ($operaciones === []) {
            throw ValidationException::withMessages([
                'operaciones' => 'Selecciona al menos una operación.',
            ]);
        }

        if (OperacionesImportacionAlmacen::requiereAlmacen($operaciones) && ! $almacenId) {
            throw ValidationException::withMessages([
                'almacen_id' => 'Selecciona un almacén para las operaciones elegidas.',
            ]);
        }

        if ($almacenId) {
            $user = \App\Models\User::query()->findOrFail($userId);
            $this->alcance->asegurarAlmacenOperable($user, $almacenId);
        }

        $ruta = $this->vistaPrevia->guardar($userId, $archivo);
        $hash = hash_file('sha256', Storage::path($ruta));

        $lote = ImportacionAlmacenLog::create([
            'user_id' => $userId,
            'tipo' => 'hub',
            'almacen_id' => $almacenId,
            'archivo_ruta' => $ruta,
            'archivo_hash' => $hash,
            'mapping' => [],
            'operaciones' => $operaciones,
            'estado' => 'borrador',
        ]);

        $destino = "importaciones_almacenes/{$lote->id}/source.".($archivo->getClientOriginalExtension() ?: 'csv');
        Storage::makeDirectory(dirname($destino));
        Storage::move($ruta, $destino);
        $headers = $this->encabezados->ejecutar($destino);
        $lote->update([
            'archivo_ruta' => $destino,
            'payload' => ['headers' => $headers],
        ]);

        return [
            'lote' => $lote->fresh(),
            'headers' => $headers,
        ];
    }
}
