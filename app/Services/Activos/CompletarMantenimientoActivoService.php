<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoMantenimiento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompletarMantenimientoActivoService
{
    public function __construct(
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private NotificarActivoService $notificarActivo,
    ) {}

    public function ejecutar(Activo $activo, ActivoMantenimiento $mantenimiento, User $actor, ?string $notas = null): Activo
    {
        if (!in_array($mantenimiento->estado, ['programado', 'en_proceso'], true)) {
            throw ValidationException::withMessages([
                'mantenimiento' => 'Este mantenimiento ya fue cerrado.',
            ]);
        }

        return DB::transaction(function () use ($activo, $mantenimiento, $actor, $notas) {
            $mantenimiento->update([
                'estado' => 'completado',
                'fecha_fin' => now()->toDateString(),
                'notas' => $notas ?? $mantenimiento->notas,
            ]);

            $activo->update(['estado' => 'disponible']);

            $this->registrarMovimiento->ejecutar($activo, $actor, 'cambio_estado', [
                'estado_anterior' => 'mantenimiento',
                'estado_nuevo' => 'disponible',
                'motivo' => 'Mantenimiento completado',
                'notas' => $notas,
            ]);

            $activoActualizado = $activo->fresh(['tipo', 'departamento', 'area', 'responsable', 'mantenimientos']);

            $this->notificarActivo->ejecutar(
                $activoActualizado,
                'activo_mantenimiento',
                'Mantenimiento completado — activo disponible',
                $actor,
            );

            return $activoActualizado;
        });
    }
}
