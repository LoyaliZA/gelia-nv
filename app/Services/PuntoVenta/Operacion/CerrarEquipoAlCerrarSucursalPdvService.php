<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
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
}
