<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ConsultaVendedoresElegiblesPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsignarReatencionTurnoPdvService
{
    use ResuelveIdempotenciaTurnoPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ConsultaVendedoresElegiblesPdvService $vendedoresElegibles,
        private readonly ConsultaPersonaDisponiblePdv $consultaDisponible,
        private readonly AsignarTurnoPdvService $asignarTurno,
    ) {}

    /**
     * @return array{
     *     turno: TurnoPdv,
     *     persona: User,
     *     atencion: TurnoPdvAtencion,
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
            PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR,
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
            $reintento = $this->resolverReintentoIdempotente($idempotencyKey, TurnoPdvEvento::TIPO_REATENCION);
            if ($reintento !== null) {
                $snapshot = is_array($reintento['evento']->snapshot_json) ? $reintento['evento']->snapshot_json : [];
                $atencion = TurnoPdvAtencion::query()->find($snapshot['atencion_id'] ?? $reintento['evento']->atencion_id);
                $persona = User::query()->find($snapshot['user_id'] ?? $atencion?->user_id);

                return [
                    'turno' => $reintento['turno'],
                    'persona' => $persona ?? new User,
                    'atencion' => $atencion ?? new TurnoPdvAtencion,
                    'evento' => $reintento['evento'],
                ];
            }

            $turnoBloqueado = TurnoPdv::query()
                ->whereKey($turno->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertVersionTurno($turnoBloqueado, $versionEsperada);

            if ($turnoBloqueado->estado !== TurnoPdv::ESTADO_EN_REATENCION) {
                throw ValidationException::withMessages([
                    'turno' => 'El turno no está en ventana de re-atención.',
                ]);
            }

            if ($turnoBloqueado->atencion_actual_id !== null) {
                throw ValidationException::withMessages([
                    'turno' => 'El turno ya tiene una atención activa.',
                ]);
            }

            if ($turnoBloqueado->reatencion_expira_at === null || $ahora->greaterThanOrEqualTo($turnoBloqueado->reatencion_expira_at)) {
                throw ValidationException::withMessages([
                    'turno' => 'La ventana de re-atención ya venció.',
                ]);
            }

            $atencionPrevia = TurnoPdvAtencion::query()
                ->where('turno_id', $turnoBloqueado->id)
                ->whereNotNull('fin_at')
                ->orderByDesc('numero_secuencia')
                ->first();

            $vendedorAnteriorId = $atencionPrevia instanceof TurnoPdvAtencion
                ? (int) $atencionPrevia->user_id
                : null;

            $destino = User::query()->find($destinoUserId);
            if (! $destino instanceof User) {
                throw ValidationException::withMessages([
                    'destino_user_id' => 'La persona destino no existe.',
                ]);
            }

            if ($vendedorAnteriorId !== null && (int) $destino->id === $vendedorAnteriorId) {
                throw ValidationException::withMessages([
                    'destino_user_id' => 'Debe asignar la re-atención a un vendedor distinto al anterior.',
                ]);
            }

            $sucursalId = (int) $turnoBloqueado->sucursal_id;

            if (! $this->vendedoresElegibles->esElegible($destino, $sucursalId)) {
                throw ValidationException::withMessages([
                    'destino_user_id' => 'La persona destino no es elegible en esta sucursal.',
                ]);
            }

            if (! $this->consultaDisponible->esDisponible($destino, $sucursalId)) {
                throw ValidationException::withMessages([
                    'destino_user_id' => 'La persona destino no está disponible.',
                ]);
            }

            $numeroSecuencia = (int) TurnoPdvAtencion::query()
                ->where('turno_id', $turnoBloqueado->id)
                ->max('numero_secuencia');

            $atencion = TurnoPdvAtencion::query()->create([
                'turno_id' => $turnoBloqueado->id,
                'user_id' => $destino->id,
                'numero_secuencia' => $numeroSecuencia + 1,
                'inicio_at' => $ahora,
                'version' => 1,
            ]);

            $versionAnterior = (int) $turnoBloqueado->version;
            $actualizado = TurnoPdv::query()
                ->whereKey($turnoBloqueado->id)
                ->where('version', $versionAnterior)
                ->update([
                    'estado' => TurnoPdv::ESTADO_ASIGNADO,
                    'atencion_actual_id' => $atencion->id,
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
                    'tipo_evento' => TurnoPdvEvento::TIPO_REATENCION,
                    'estado_anterior' => TurnoPdv::ESTADO_EN_REATENCION,
                    'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'user_id' => $destino->id,
                        'atencion_id' => $atencion->id,
                        'origen' => 'gerencia.reatencion',
                        'vendedor_anterior_id' => $vendedorAnteriorId,
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $recuperado = $this->manejarColisionIdempotencia(
                    $exception,
                    $idempotencyKey,
                    TurnoPdvEvento::TIPO_REATENCION,
                );
                if ($recuperado !== null) {
                    $eventoRecuperado = $recuperado['evento'];
                    $snapshot = is_array($eventoRecuperado->snapshot_json) ? $eventoRecuperado->snapshot_json : [];
                    $atencionRecuperada = TurnoPdvAtencion::query()->find($snapshot['atencion_id'] ?? null);
                    $personaRecuperada = User::query()->find($snapshot['user_id'] ?? $atencionRecuperada?->user_id);

                    return [
                        'turno' => $recuperado['turno'],
                        'persona' => $personaRecuperada ?? $destino,
                        'atencion' => $atencionRecuperada ?? $atencion->fresh(),
                        'evento' => $eventoRecuperado,
                    ];
                }

                throw $exception;
            }

            $turnoActualizado = $turnoBloqueado->fresh(['cliente', 'sucursal', 'atencionActual']);

            $resultado = [
                'turno' => $turnoActualizado,
                'persona' => $destino,
                'atencion' => $atencion,
                'evento' => $evento,
                'es_reatencion' => true,
            ];

            $this->asignarTurno->publicarEventoDominio($resultado);

            return [
                'turno' => $turnoActualizado,
                'persona' => $destino,
                'atencion' => $atencion,
                'evento' => $evento,
            ];
        });
    }
}
