<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\TurnoTransferido;
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

class TransferirTurnoPdvService
{
    use ResuelveIdempotenciaTurnoPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{
     *     turno: TurnoPdv,
     *     atencion_anterior: TurnoPdvAtencion,
     *     atencion_nueva: TurnoPdvAtencion,
     *     evento: TurnoPdvEvento
     * }
     */
    public function ejecutar(
        TurnoPdv $turno,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        int $destinoUserId,
        CarbonInterface $ahora,
    ): array {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR,
            (int) $turno->sucursal_id,
        );

        return DB::transaction(function () use (
            $turno,
            $actor,
            $versionEsperada,
            $idempotencyKey,
            $destinoUserId,
            $ahora,
        ): array {
            $reintento = $this->resolverReintentoIdempotente($idempotencyKey, TurnoPdvEvento::TIPO_TRANSFERIDO);
            if ($reintento !== null) {
                $evento = $reintento['evento'];
                $snapshot = is_array($evento->snapshot_json) ? $evento->snapshot_json : [];
                $atencionAnterior = TurnoPdvAtencion::query()->find($snapshot['atencion_anterior_id'] ?? null);
                $atencionNueva = TurnoPdvAtencion::query()->find($snapshot['atencion_nueva_id'] ?? null);

                return [
                    'turno' => $reintento['turno'],
                    'atencion_anterior' => $atencionAnterior ?? new TurnoPdvAtencion,
                    'atencion_nueva' => $atencionNueva ?? new TurnoPdvAtencion,
                    'evento' => $evento,
                ];
            }

            $turnoBloqueado = TurnoPdv::query()
                ->whereKey($turno->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersionTurno($turnoBloqueado, $versionEsperada);

            if ($turnoBloqueado->estado !== TurnoPdv::ESTADO_ASIGNADO) {
                throw ValidationException::withMessages([
                    'turno' => 'Solo se puede transferir un turno asignado.',
                ]);
            }

            $atencionAnterior = TurnoPdvAtencion::query()
                ->whereKey($turnoBloqueado->atencion_actual_id)
                ->lockForUpdate()
                ->first();

            if (! $atencionAnterior instanceof TurnoPdvAtencion || $atencionAnterior->fin_at !== null) {
                throw ValidationException::withMessages([
                    'turno' => 'El turno no tiene una atención activa para transferir.',
                ]);
            }

            $destino = User::query()->find($destinoUserId);
            if (! $destino instanceof User) {
                throw ValidationException::withMessages([
                    'destino_user_id' => 'La persona destino no existe.',
                ]);
            }

            if ((int) $destino->id === (int) $atencionAnterior->user_id) {
                throw ValidationException::withMessages([
                    'destino_user_id' => 'Debe elegir una persona distinta a quien atiende actualmente.',
                ]);
            }

            if ($this->personaTieneAtencionAbierta($destino->id)) {
                throw ValidationException::withMessages([
                    'destino_user_id' => 'La persona destino no está disponible.',
                ]);
            }

            $atencionAnterior->update([
                'fin_at' => $ahora,
                'motivo_cierre' => MotivosCierreAtencionTurnoPdv::TRANSFERENCIA,
            ]);

            $numeroSecuencia = (int) TurnoPdvAtencion::query()
                ->where('turno_id', $turnoBloqueado->id)
                ->max('numero_secuencia');

            $atencionNueva = TurnoPdvAtencion::query()->create([
                'turno_id' => $turnoBloqueado->id,
                'user_id' => $destino->id,
                'numero_secuencia' => $numeroSecuencia + 1,
                'inicio_at' => $ahora,
                'es_transferencia' => true,
                'transferido_por_id' => $actor->id,
                'version' => 1,
            ]);

            $versionAnterior = (int) $turnoBloqueado->version;
            $actualizado = TurnoPdv::query()
                ->whereKey($turnoBloqueado->id)
                ->where('version', $versionAnterior)
                ->update([
                    'atencion_actual_id' => $atencionNueva->id,
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
                    'atencion_id' => $atencionNueva->id,
                    'tipo_evento' => TurnoPdvEvento::TIPO_TRANSFERIDO,
                    'estado_anterior' => TurnoPdv::ESTADO_ASIGNADO,
                    'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'destino_user_id' => $destino->id,
                        'atencion_anterior_id' => $atencionAnterior->id,
                        'atencion_nueva_id' => $atencionNueva->id,
                        'origen_user_id' => $atencionAnterior->user_id,
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $recuperado = $this->manejarColisionIdempotencia(
                    $exception,
                    $idempotencyKey,
                    TurnoPdvEvento::TIPO_TRANSFERIDO,
                );
                if ($recuperado !== null) {
                    $eventoRecuperado = $recuperado['evento'];
                    $snapshot = is_array($eventoRecuperado->snapshot_json) ? $eventoRecuperado->snapshot_json : [];

                    return [
                        'turno' => $recuperado['turno'],
                        'atencion_anterior' => TurnoPdvAtencion::query()->find($snapshot['atencion_anterior_id'] ?? null)
                            ?? $atencionAnterior->fresh(),
                        'atencion_nueva' => TurnoPdvAtencion::query()->find($snapshot['atencion_nueva_id'] ?? null)
                            ?? $atencionNueva->fresh(),
                        'evento' => $eventoRecuperado,
                    ];
                }

                throw $exception;
            }

            $turnoActualizado = $turnoBloqueado->fresh(['cliente', 'sucursal', 'atencionActual']);

            TurnoTransferido::dispatch(
                $turnoActualizado,
                $atencionAnterior->fresh(),
                $atencionNueva->fresh(),
                $evento,
                (int) $turnoBloqueado->sucursal_id,
            );

            return [
                'turno' => $turnoActualizado,
                'atencion_anterior' => $atencionAnterior->fresh(),
                'atencion_nueva' => $atencionNueva->fresh(),
                'evento' => $evento,
            ];
        });
    }

    private function personaTieneAtencionAbierta(int $userId): bool
    {
        return TurnoPdvAtencion::query()
            ->where('user_id', $userId)
            ->whereNull('fin_at')
            ->exists();
    }
}
