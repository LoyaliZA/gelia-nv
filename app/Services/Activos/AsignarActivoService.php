<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsignarActivoService
{
    public function __construct(
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private NotificarActivoService $notificarActivo,
        private ConstruirSnapshotActivoService $construirSnapshot,
    ) {}

    public function ejecutar(Activo $activo, User $actor, int $userId, ?string $notas = null, ?string $condicionesEntrega = null, bool $asignarAccesorios = true): Activo
    {
        if (!in_array($activo->estado, ['disponible', 'asignado'], true)) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede asignar un activo en estado ' . $activo->estado . '.',
            ]);
        }

        $destinatario = User::findOrFail($userId);

        if ($activo->responsable_user_id === $destinatario->id) {
            throw ValidationException::withMessages([
                'user_id' => 'El activo ya pertenece a este usuario.',
            ]);
        }

        $activoActualizado = DB::transaction(function () use ($activo, $actor, $destinatario, $notas, $condicionesEntrega) {
            $activo->loadMissing(['responsable', 'tipo', 'departamento']);
            $snapshot = $this->construirSnapshot->ejecutar($activo);
            $tipoMovimiento = $activo->responsable_user_id ? 'reasignacion' : 'asignacion';
            $responsableAnterior = $activo->responsable;

            if ($activo->responsable_user_id) {
                ActivoAsignacion::where('activo_id', $activo->id)
                    ->where('activa', true)
                    ->update([
                        'activa' => false,
                        'fecha_fin' => now()->toDateString(),
                    ]);
            }

            ActivoAsignacion::create([
                'activo_id' => $activo->id,
                'user_id' => $destinatario->id,
                'asignado_por_id' => $actor->id,
                'fecha_inicio' => now()->toDateString(),
                'activa' => true,
                'notas' => $notas,
                'condiciones_entrega' => $condicionesEntrega,
                'firmado' => false,
            ]);

            $estadoAnterior = $activo->estado;
            $activo->update([
                'responsable_user_id' => $destinatario->id,
                'estado' => 'asignado',
            ]);

            $this->registrarMovimiento->ejecutar($activo, $actor, $tipoMovimiento, [
                'user_destino_id' => $destinatario->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'asignado',
                'notas' => $notas,
                'datos_snapshot' => $snapshot,
            ]);

            $activoActualizado = $activo->fresh(['tipo', 'departamento', 'area', 'responsable', 'asignaciones.usuario']);

            $mensaje = $tipoMovimiento === 'reasignacion'
                ? "Activo reasignado a {$destinatario->name}"
                : "Activo asignado a {$destinatario->name}";

            $this->notificarActivo->ejecutar(
                $activoActualizado,
                'activo_asignado',
                $mensaje,
                $actor,
                $destinatario,
                $responsableAnterior,
            );

            return $activoActualizado;
        });

        if ($asignarAccesorios) {
            $this->asignarAccesoriosVinculados(
                $activoActualizado,
                $actor,
                $destinatario,
                $notas,
                $condicionesEntrega,
            );
        }

        return $activoActualizado->fresh(['tipo', 'departamento', 'area', 'responsable', 'asignaciones.usuario']);
    }

    private function asignarAccesoriosVinculados(
        Activo $activo,
        User $actor,
        User $destinatario,
        ?string $notas,
        ?string $condicionesEntrega,
    ): void {
        $activo->loadMissing('accesorios');

        foreach ($activo->accesorios as $accesorio) {
            if ($accesorio->estado === 'baja') {
                continue;
            }

            if ($accesorio->responsable_user_id === $destinatario->id) {
                continue;
            }

            if (!in_array($accesorio->estado, ['disponible', 'asignado'], true)) {
                continue;
            }

            $this->ejecutar($accesorio, $actor, $destinatario->id, $notas, $condicionesEntrega, false);
        }
    }
}
