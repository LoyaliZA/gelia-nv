<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarEstadoActivoService
{
    public function __construct(
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private NotificarActivoService $notificarActivo,
        private ConstruirSnapshotActivoService $construirSnapshot,
    ) {}

    public function ejecutar(Activo $activo, User $actor, string $nuevoEstado, ?string $motivo = null, ?string $notas = null): Activo
    {
        if (!in_array($nuevoEstado, Activo::ESTADOS, true)) {
            throw ValidationException::withMessages([
                'estado' => 'Estado no válido.',
            ]);
        }

        if ($activo->estado === $nuevoEstado) {
            throw ValidationException::withMessages([
                'estado' => 'El activo ya se encuentra en ese estado.',
            ]);
        }

        $this->validarTransicion($activo, $nuevoEstado);

        return DB::transaction(function () use ($activo, $actor, $nuevoEstado, $motivo, $notas) {
            $activo->loadMissing(['responsable', 'tipo', 'departamento']);
            $snapshot = $this->construirSnapshot->ejecutar($activo);
            $estadoAnterior = $activo->estado;
            $responsableAnterior = $activo->responsable;
            $updates = ['estado' => $nuevoEstado];

            if (in_array($nuevoEstado, ['disponible', 'mantenimiento', 'baja'], true)) {
                ActivoAsignacion::where('activo_id', $activo->id)
                    ->where('activa', true)
                    ->update([
                        'activa' => false,
                        'fecha_fin' => now()->toDateString(),
                    ]);
                $updates['responsable_user_id'] = null;
            }

            $activo->update($updates);

            $tipo = $nuevoEstado === 'baja' ? 'baja' : 'cambio_estado';

            $this->registrarMovimiento->ejecutar($activo, $actor, $tipo, [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado,
                'motivo' => $motivo,
                'notas' => $notas,
                'datos_snapshot' => $snapshot,
            ]);

            $activoActualizado = $activo->fresh(['tipo', 'departamento', 'area', 'responsable']);

            if ($nuevoEstado === 'baja') {
                $this->notificarActivo->ejecutar(
                    $activoActualizado,
                    'activo_baja',
                    'Activo dado de baja',
                    $actor,
                    null,
                    $responsableAnterior,
                );
            }

            return $activoActualizado;
        });
    }

    private function validarTransicion(Activo $activo, string $nuevoEstado): void
    {
        if ($activo->estado === 'baja') {
            throw ValidationException::withMessages([
                'estado' => 'Un activo dado de baja no puede cambiar de estado.',
            ]);
        }

        if ($nuevoEstado === 'asignado') {
            throw ValidationException::withMessages([
                'estado' => 'Use la asignación para vincular el activo a un usuario.',
            ]);
        }
    }
}
