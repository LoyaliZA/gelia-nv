<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Carbon\CarbonInterface;

class CerrarEquipoAlCerrarSucursalPdvService
{
    public function __construct(
        private readonly CerrarJornadaPdvService $cerrarJornada,
        private readonly OperacionPdvConfig $config,
    ) {}

    public function cerrarEquipoDelDia(int $sucursalId, CarbonInterface $ahora, int $actorId): void
    {
        $jornadas = JornadaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->lockForUpdate()
            ->get();

        foreach ($jornadas as $jornada) {
            $this->cerrarJornada->cerrarParaUsuario(
                (int) $jornada->user_id,
                $sucursalId,
                (int) $jornada->version,
                $ahora,
                $actorId,
            );
        }
    }

    public function cerrarJornadasDeDiasAnteriores(int $sucursalId, CarbonInterface $ahora, int $actorId): void
    {
        $inicioDia = $this->config->inicioDiaOperativo($sucursalId, $ahora);

        $jornadas = JornadaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->where('apertura_at', '<', $inicioDia)
            ->lockForUpdate()
            ->get();

        foreach ($jornadas as $jornada) {
            $tieneAtencion = TurnoPdvAtencion::query()
                ->where('user_id', $jornada->user_id)
                ->whereNull('fin_at')
                ->exists();

            if ($tieneAtencion) {
                continue;
            }

            $this->cerrarJornada->cerrarParaUsuario(
                (int) $jornada->user_id,
                $sucursalId,
                (int) $jornada->version,
                $ahora,
                $actorId,
            );
        }
    }

    public function retirarColaDeDiasAnteriores(int $sucursalId, CarbonInterface $ahora): int
    {
        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);

        return $this->retirarCola($sucursalId, $ahora, $fechaOperativa, true);
    }

    public function retirarColaDeFecha(int $sucursalId, string $fechaOperativa, CarbonInterface $ahora): int
    {
        return $this->retirarCola($sucursalId, $ahora, $fechaOperativa, false);
    }

    private function retirarCola(
        int $sucursalId,
        CarbonInterface $ahora,
        string $fechaOperativa,
        bool $anteriores,
    ): int {
        $consulta = TurnoPdv::query()
            ->where('sucursal_id', $sucursalId);

        if ($anteriores) {
            $consulta->whereIn('estado', [
                TurnoPdv::ESTADO_EN_COLA,
                TurnoPdv::ESTADO_ASIGNADO,
                TurnoPdv::ESTADO_EN_REATENCION,
            ])->whereDate('fecha_operativa', '<', $fechaOperativa);
        } else {
            $consulta->where('estado', TurnoPdv::ESTADO_EN_COLA)
                ->whereNull('atencion_actual_id')
                ->whereDate('fecha_operativa', $fechaOperativa);
        }

        $retirados = 0;

        foreach ($consulta->lockForUpdate()->get() as $turno) {
            if ($this->cerrarTurnoPorCambioDeDia($turno, $ahora)) {
                $retirados++;
            }
        }

        return $retirados;
    }

    private function cerrarTurnoPorCambioDeDia(TurnoPdv $turno, CarbonInterface $ahora): bool
    {
        $clave = 'pdv:cierre-cola:'.$turno->id;
        if (TurnoPdvEvento::query()->where('idempotency_key', $clave)->exists()) {
            return false;
        }

        $estadoAnterior = (string) $turno->estado;
        $versionAnterior = (int) $turno->version;

        TurnoPdvAtencion::query()
            ->where('turno_id', $turno->id)
            ->whereNull('fin_at')
            ->update(['fin_at' => $ahora]);

        $actualizado = TurnoPdv::query()
            ->whereKey($turno->id)
            ->where('estado', $estadoAnterior)
            ->where('version', $versionAnterior)
            ->update([
                'estado' => TurnoPdv::ESTADO_CERRADO,
                'cerrado_at' => $ahora,
                'atencion_actual_id' => null,
                'baja_at' => $ahora,
                'baja_motivo' => 'cierre_jornada',
                'baja_motivo_detalle' => 'La jornada terminó y el turno no fue atendido.',
                'version' => $versionAnterior + 1,
            ]);

        if ($actualizado !== 1) {
            return false;
        }

        TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_BAJA_COLA,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => TurnoPdv::ESTADO_CERRADO,
            'actor_id' => null,
            'ocurrido_at' => $ahora,
            'snapshot_json' => [
                'motivo' => 'cierre_jornada',
                'fecha_operativa' => $turno->fecha_operativa?->toDateString(),
            ],
            'idempotency_key' => $clave,
        ]);

        return true;
    }
}
