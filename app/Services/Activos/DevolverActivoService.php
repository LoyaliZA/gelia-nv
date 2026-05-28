<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DevolverActivoService
{
    public function __construct(
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private NotificarActivoService $notificarActivo,
    ) {}

    public function ejecutar(Activo $activo, User $actor, ?string $notas = null): Activo
    {
        if ($activo->estado !== 'asignado' || !$activo->responsable_user_id) {
            throw ValidationException::withMessages([
                'estado' => 'El activo no tiene una asignación activa para devolver.',
            ]);
        }

        return DB::transaction(function () use ($activo, $actor, $notas) {
            $activo->loadMissing('responsable');
            $responsableAnterior = $activo->responsable;

            ActivoAsignacion::where('activo_id', $activo->id)
                ->where('activa', true)
                ->update([
                    'activa' => false,
                    'fecha_fin' => now()->toDateString(),
                ]);

            $estadoAnterior = $activo->estado;
            $activo->update([
                'responsable_user_id' => null,
                'estado' => 'disponible',
            ]);

            $this->registrarMovimiento->ejecutar($activo, $actor, 'devolucion', [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'disponible',
                'notas' => $notas,
            ]);

            $activoActualizado = $activo->fresh(['tipo', 'departamento', 'area', 'responsable', 'asignaciones.usuario']);

            $this->notificarActivo->ejecutar(
                $activoActualizado,
                'activo_devuelto',
                'Activo devuelto al inventario disponible',
                $actor,
                null,
                $responsableAnterior,
            );

            return $activoActualizado;
        });
    }
}
