<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferirActivoService
{
    public function __construct(
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private NotificarActivoService $notificarActivo,
        private ConstruirSnapshotActivoService $construirSnapshot,
    ) {}

    public function ejecutar(Activo $activo, User $actor, int $departamentoDestinoId, ?string $motivo = null, ?string $notas = null): Activo
    {
        if ($activo->estado === 'baja') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede transferir un activo dado de baja.',
            ]);
        }

        if ($activo->departamento_id === $departamentoDestinoId) {
            throw ValidationException::withMessages([
                'departamento_destino_id' => 'El activo ya pertenece a ese departamento.',
            ]);
        }

        return DB::transaction(function () use ($activo, $actor, $departamentoDestinoId, $motivo, $notas) {
            $activo->loadMissing(['responsable', 'tipo', 'departamento']);
            $snapshot = $this->construirSnapshot->ejecutar($activo);
            $departamentoOrigenId = $activo->departamento_id;
            $estadoAnterior = $activo->estado;
            $responsableAnterior = $activo->responsable;

            if ($activo->responsable_user_id) {
                ActivoAsignacion::where('activo_id', $activo->id)
                    ->where('activa', true)
                    ->update([
                        'activa' => false,
                        'fecha_fin' => now()->toDateString(),
                    ]);
            }

            $activo->update([
                'departamento_id' => $departamentoDestinoId,
                'area_id' => null,
                'responsable_user_id' => null,
                'estado' => 'disponible',
            ]);

            $this->registrarMovimiento->ejecutar($activo, $actor, 'transferencia', [
                'departamento_origen_id' => $departamentoOrigenId,
                'departamento_destino_id' => $departamentoDestinoId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'disponible',
                'motivo' => $motivo,
                'notas' => $notas,
                'datos_snapshot' => $snapshot,
            ]);

            $activoActualizado = $activo->fresh(['tipo', 'departamento', 'area', 'responsable']);

            $this->notificarActivo->ejecutar(
                $activoActualizado,
                'activo_transferido',
                'Activo transferido a otro departamento',
                $actor,
                null,
                $responsableAnterior,
            );

            return $activoActualizado;
        });
    }
}
