<?php

namespace App\Services\Almacenes;

use App\Jobs\Almacenes\ImportarAlmacenCatalogoJob;
use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Models\User;
use App\Support\Almacenes\OperacionesImportacionAlmacen;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AplicarLoteImportacionAlmacenService
{
    public function __construct(
        private readonly AlcanceAlmacenesService $alcance,
    ) {}

    public function ejecutar(ImportacionAlmacenLog $lote, User $user, array $mapping): array
    {
        if ($lote->user_id !== $user->id && ! $user->can('catalogos.gestionar')) {
            abort(403);
        }

        if ($lote->almacen_id) {
            $this->alcance->asegurarAlmacenOperable($user, (int) $lote->almacen_id);
        }

        if ($lote->estado !== 'simulado') {
            throw ValidationException::withMessages([
                'lote' => $lote->estado === 'borrador'
                    ? 'Debes ejecutar la simulación antes de aplicar los cambios.'
                    : 'Este lote ya fue aplicado o está en proceso.',
            ]);
        }

        if ($lote->resumen_simulacion === null) {
            throw ValidationException::withMessages([
                'lote' => 'No hay resumen de simulación. Vuelve a simular los cambios.',
            ]);
        }

        $operaciones = $lote->operaciones ?? [];
        $this->assertPermisosOperaciones($user, $operaciones);

        $activo = ImportacionAlmacenLog::activo();
        if ($activo && $activo->id !== $lote->id) {
            throw ValidationException::withMessages([
                'lote' => "Hay otra importación en curso (#{$activo->id}). Espera a que termine o cancélala desde el indicador flotante.",
            ]);
        }

        if (! Storage::exists($lote->archivo_ruta)) {
            throw ValidationException::withMessages([
                'lote' => 'Archivo del lote no encontrado.',
            ]);
        }

        $hashActual = hash_file('sha256', Storage::path($lote->archivo_ruta));
        if ($lote->archivo_hash && $hashActual !== $lote->archivo_hash) {
            throw ValidationException::withMessages([
                'lote' => 'El archivo cambió desde la simulación. Vuelve a analizar.',
            ]);
        }

        $lote->update([
            'mapping' => $mapping,
            'estado' => 'pendiente',
            'archivo_normalizado' => null,
            'procesados' => 0,
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
        ]);

        ImportarAlmacenCatalogoJob::dispatch($lote->id, 0);

        return ['log_id' => $lote->id];
    }

    /**
     * @param  list<string>  $operaciones
     */
    private function assertPermisosOperaciones(User $user, array $operaciones): void
    {
        if ($user->can('catalogos.gestionar')) {
            return;
        }

        foreach ($operaciones as $operacion) {
            $permiso = OperacionesImportacionAlmacen::permisoParaOperacion($operacion);
            if ($permiso && ! $user->can($permiso) && ! $user->can('gestion_interna.productos.gestionar') && ! $user->can('almacenes.productos.gestionar')) {
                if ($operacion === OperacionesImportacionAlmacen::FICHA_PRODUCTO
                    && ($user->can('gestion_interna.productos.gestionar') || $user->can('almacenes.productos.gestionar'))) {
                    continue;
                }
                throw ValidationException::withMessages([
                    'operaciones' => "No tienes permiso para aplicar: {$operacion}.",
                ]);
            }
        }
    }
}
