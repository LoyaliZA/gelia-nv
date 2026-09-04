<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\AtencionCerrada;
use App\Jobs\PuntoVenta\Turnos\VencerVentanaReatencionTurnoPdvJob;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CerrarAtencionTurnoPdvService
{
    use ResuelveIdempotenciaTurnoPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    /**
     * @return array{turno: TurnoPdv, atencion: TurnoPdvAtencion, evento: TurnoPdvEvento}
     */
    public function ejecutar(
        TurnoPdv $turno,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $motivo,
        ?string $motivoDetalle,
        CarbonInterface $ahora,
    ): array {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            (int) $turno->sucursal_id,
        );

        if (! MotivosCierreAtencionTurnoPdv::esValidoOperador($motivo)) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de cierre no es válido.',
            ]);
        }

        if ($motivo === MotivosCierreAtencionTurnoPdv::OTRO && trim((string) $motivoDetalle) === '') {
            throw ValidationException::withMessages([
                'motivo_detalle' => 'Debe indicar el detalle cuando el motivo es "otro".',
            ]);
        }

        return DB::transaction(function () use (
            $turno,
            $actor,
            $versionEsperada,
            $idempotencyKey,
            $motivo,
            $motivoDetalle,
            $ahora,
        ): array {
            $reintento = $this->resolverReintentoIdempotente($idempotencyKey, TurnoPdvEvento::TIPO_ATENCION_CERRADA);
            if ($reintento !== null) {
                $atencion = TurnoPdvAtencion::query()->find($reintento['evento']->atencion_id);

                return [
                    'turno' => $reintento['turno'],
                    'atencion' => $atencion ?? new TurnoPdvAtencion,
                    'evento' => $reintento['evento'],
                ];
            }

            $turnoBloqueado = TurnoPdv::query()
                ->whereKey($turno->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersionTurno($turnoBloqueado, $versionEsperada);

            if ($turnoBloqueado->estado !== TurnoPdv::ESTADO_ASIGNADO) {
                throw ValidationException::withMessages([
                    'turno' => 'Solo se puede cerrar atención en turnos asignados.',
                ]);
            }

            $atencion = TurnoPdvAtencion::query()
                ->whereKey($turnoBloqueado->atencion_actual_id)
                ->lockForUpdate()
                ->first();

            if (! $atencion instanceof TurnoPdvAtencion || $atencion->fin_at !== null) {
                throw ValidationException::withMessages([
                    'turno' => 'El turno no tiene una atención activa para cerrar.',
                ]);
            }

            if ((int) $atencion->user_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'turno' => 'Solo quien atiende puede cerrar esta atención.',
                ]);
            }

            $plazos = $this->plazos->obtener();
            $reatencionExpira = $ahora->copy()->addMinutes($plazos['ventana_reatencion_minutos']);

            $atencion->update([
                'fin_at' => $ahora,
                'motivo_cierre' => $motivo,
                'motivo_cierre_detalle' => $motivoDetalle,
            ]);

            $versionAnterior = (int) $turnoBloqueado->version;
            $actualizado = TurnoPdv::query()
                ->whereKey($turnoBloqueado->id)
                ->where('version', $versionAnterior)
                ->update([
                    'estado' => TurnoPdv::ESTADO_EN_REATENCION,
                    'atencion_actual_id' => null,
                    'reatencion_expira_at' => $reatencionExpira,
                    'version' => $versionAnterior + 1,
                ]);

            if ($actualizado !== 1) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó este turno. Actualice la página e intente de nuevo.',
                ]);
            }

            try {
                $evento = TurnoPdvEvento::query()->create([
                    'turno_id' => $turnoBloqueado->id,
                    'atencion_id' => $atencion->id,
                    'tipo_evento' => TurnoPdvEvento::TIPO_ATENCION_CERRADA,
                    'estado_anterior' => TurnoPdv::ESTADO_ASIGNADO,
                    'estado_nuevo' => TurnoPdv::ESTADO_EN_REATENCION,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'motivo' => $motivo,
                        'motivo_detalle' => $motivoDetalle,
                        'reatencion_expira_at' => $reatencionExpira->toIso8601String(),
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $recuperado = $this->manejarColisionIdempotencia(
                    $exception,
                    $idempotencyKey,
                    TurnoPdvEvento::TIPO_ATENCION_CERRADA,
                );
                if ($recuperado !== null) {
                    $atencionRecuperada = TurnoPdvAtencion::query()
                        ->where('turno_id', $recuperado['turno']->id)
                        ->latest('id')
                        ->first();

                    return [
                        'turno' => $recuperado['turno'],
                        'atencion' => $atencionRecuperada ?? $atencion->fresh(),
                        'evento' => $recuperado['evento'],
                    ];
                }

                throw $exception;
            }

            VencerVentanaReatencionTurnoPdvJob::dispatch($turnoBloqueado->id)
                ->delay($reatencionExpira);

            $turnoActualizado = $turnoBloqueado->fresh(['cliente', 'sucursal', 'atencionActual']);
            $atencionActualizada = $atencion->fresh();

            AtencionCerrada::dispatch(
                $turnoActualizado,
                $atencionActualizada,
                $evento,
                (int) $turnoBloqueado->sucursal_id,
            );

            return [
                'turno' => $turnoActualizado,
                'atencion' => $atencionActualizada,
                'evento' => $evento,
            ];
        });
    }
}
