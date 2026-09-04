<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoReatencion;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;
use Carbon\CarbonInterface;

class AsignarTurnoPdvService
{
    public function __construct(
        private readonly ConsultaPersonaDisponiblePdv $consultaDisponible,
    ) {}

    /**
     * @return array{
     *     turno: TurnoPdv,
     *     persona: User,
     *     atencion: TurnoPdvAtencion,
     *     evento: TurnoPdvEvento,
     *     es_reatencion: bool
     * }|null
     */
    public function ejecutar(TurnoPdv $turno, CarbonInterface $ahora, string $origenAsignacion): ?array
    {
        $turnoBloqueado = TurnoPdv::query()
            ->whereKey($turno->id)
            ->lockForUpdate()
            ->first();

        if (! $turnoBloqueado instanceof TurnoPdv) {
            return null;
        }

        if (! in_array($turnoBloqueado->estado, [
            TurnoPdv::ESTADO_EN_COLA,
            TurnoPdv::ESTADO_EN_REATENCION,
        ], true)) {
            return null;
        }

        if ($turnoBloqueado->atencion_actual_id !== null) {
            return null;
        }

        $persona = $this->consultaDisponible->primeraDisponible(
            $turnoBloqueado->sucursal_id,
            $turnoBloqueado->servicio,
        );

        if (! $persona instanceof User) {
            return null;
        }

        if ($this->personaTieneAtencionAbierta($persona->id)) {
            return null;
        }

        $estadoAnterior = $turnoBloqueado->estado;
        $esReatencion = $estadoAnterior === TurnoPdv::ESTADO_EN_REATENCION;

        $numeroSecuencia = (int) TurnoPdvAtencion::query()
            ->where('turno_id', $turnoBloqueado->id)
            ->max('numero_secuencia');

        $atencion = TurnoPdvAtencion::query()->create([
            'turno_id' => $turnoBloqueado->id,
            'user_id' => $persona->id,
            'numero_secuencia' => $numeroSecuencia + 1,
            'inicio_at' => $ahora,
            'version' => 1,
        ]);

        $versionAnterior = $turnoBloqueado->version;

        $actualizado = TurnoPdv::query()
            ->whereKey($turnoBloqueado->id)
            ->where('version', $versionAnterior)
            ->update([
                'estado' => TurnoPdv::ESTADO_ASIGNADO,
                'atencion_actual_id' => $atencion->id,
                'version' => $versionAnterior + 1,
            ]);

        if ($actualizado !== 1) {
            return null;
        }

        $tipoEvento = $esReatencion
            ? TurnoPdvEvento::TIPO_REATENCION
            : TurnoPdvEvento::TIPO_ASIGNADO;

        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turnoBloqueado->id,
            'atencion_id' => $atencion->id,
            'tipo_evento' => $tipoEvento,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
            'actor_id' => null,
            'ocurrido_at' => $ahora,
            'snapshot_json' => [
                'user_id' => $persona->id,
                'atencion_id' => $atencion->id,
                'origen' => $origenAsignacion,
            ],
        ]);

        $turnoActualizado = $turnoBloqueado->fresh(['cliente', 'sucursal', 'altaPor', 'atencionActual']);

        return [
            'turno' => $turnoActualizado,
            'persona' => $persona,
            'atencion' => $atencion,
            'evento' => $evento,
            'es_reatencion' => $esReatencion,
        ];
    }

    /**
     * @param  array{
     *     turno: TurnoPdv,
     *     persona: User,
     *     atencion: TurnoPdvAtencion,
     *     evento: TurnoPdvEvento,
     *     es_reatencion: bool
     * }  $resultado
     */
    public function publicarEventoDominio(array $resultado): void
    {
        if ($resultado['es_reatencion']) {
            TurnoReatencion::dispatch(
                $resultado['turno'],
                $resultado['atencion'],
                $resultado['evento'],
                $resultado['turno']->sucursal_id,
            );

            return;
        }

        TurnoAsignado::dispatch(
            $resultado['turno'],
            $resultado['atencion'],
            $resultado['evento'],
            $resultado['turno']->sucursal_id,
        );
    }

    private function personaTieneAtencionAbierta(int $userId): bool
    {
        return TurnoPdvAtencion::query()
            ->where('user_id', $userId)
            ->whereNull('fin_at')
            ->exists();
    }
}
