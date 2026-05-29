<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\ActivoMantenimiento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramarMantenimientoActivoService
{
    public function __construct(
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private NotificarActivoService $notificarActivo,
        private ConstruirSnapshotActivoService $construirSnapshot,
    ) {}

    public function ejecutar(Activo $activo, User $actor, array $datos): ActivoMantenimiento
    {
        if ($activo->estado === 'baja') {
            throw ValidationException::withMessages(['estado' => 'No se puede programar mantenimiento a un activo dado de baja.']);
        }

        return DB::transaction(function () use ($activo, $actor, $datos) {
            $activo->loadMissing(['responsable', 'tipo', 'departamento']);
            $snapshot = $this->construirSnapshot->ejecutar($activo);
            $responsableAnterior = $activo->responsable;

            if ($activo->responsable_user_id) {
                ActivoAsignacion::where('activo_id', $activo->id)->where('activa', true)->update([
                    'activa' => false,
                    'fecha_fin' => now()->toDateString(),
                ]);
            }

            $estadoAnterior = $activo->estado;
            $activo->update(['estado' => 'mantenimiento', 'responsable_user_id' => null]);

            $mantenimiento = ActivoMantenimiento::create([
                'activo_id' => $activo->id,
                'usuario_id' => $actor->id,
                'tipo' => $datos['tipo'] ?? 'preventivo',
                'estado' => 'programado',
                'fecha_programada' => $datos['fecha_programada'] ?? now()->toDateString(),
                'proveedor' => $datos['proveedor'] ?? null,
                'costo' => $datos['costo'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'proximo_mantenimiento' => $datos['proximo_mantenimiento'] ?? null,
            ]);

            $this->registrarMovimiento->ejecutar($activo, $actor, 'cambio_estado', [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'mantenimiento',
                'motivo' => 'Mantenimiento programado',
                'notas' => $datos['notas'] ?? null,
                'datos_snapshot' => array_merge($snapshot, ['mantenimiento' => $mantenimiento->toArray()]),
            ]);

            $activoActualizado = $activo->fresh(['tipo', 'departamento', 'area', 'responsable']);

            $this->notificarActivo->ejecutar(
                $activoActualizado,
                'activo_mantenimiento',
                'Mantenimiento programado para el activo',
                $actor,
                null,
                $responsableAnterior,
            );

            return $mantenimiento;
        });
    }
}
