<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\SerializadorBandejaRecepcionTurnoPdv;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class ConsultaBandejaRecepcionTurnoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, CarbonInterface $ahora): array
    {
        if (! $this->alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_TURNOS_VER)) {
            throw new AuthorizationException('No tienes permiso para consultar la bandeja de turnos.');
        }

        $sucursalId = $this->alcance->sucursalActivaId($user);
        if ($sucursalId === null) {
            throw new AuthorizationException('Debes seleccionar una sucursal activa.');
        }

        $enCola = $this->consultarEnCola($sucursalId);
        $asignados = $this->consultarAsignados($sucursalId);

        return [
            'servidor_at' => $ahora->toIso8601String(),
            'en_cola' => $enCola
                ->map(fn (TurnoPdv $turno) => SerializadorBandejaRecepcionTurnoPdv::turnoEnCola($turno, $ahora))
                ->values()
                ->all(),
            'asignados' => $asignados
                ->map(fn (TurnoPdv $turno) => SerializadorBandejaRecepcionTurnoPdv::turnoAsignado($turno, $ahora))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, TurnoPdv>
     */
    private function consultarEnCola(int $sucursalId): Collection
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('servicio', TurnoPdv::SERVICIO_VENTAS)
            ->where('estado', TurnoPdv::ESTADO_EN_COLA)
            ->whereNull('atencion_actual_id')
            ->orderByRaw(
                'CASE WHEN prioridad_adulto_mayor = 1'
                .' OR prioridad_discapacidad = 1'
                .' OR prioridad_diamante = 1'
                .' OR prioridad_vip = 1 THEN 0 ELSE 1 END ASC'
            )
            ->orderBy('alta_at')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, TurnoPdv>
     */
    private function consultarAsignados(int $sucursalId): Collection
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('servicio', TurnoPdv::SERVICIO_VENTAS)
            ->where('estado', TurnoPdv::ESTADO_ASIGNADO)
            ->with(['atencionActual.user:id,name'])
            ->orderBy('alta_at')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }
}
