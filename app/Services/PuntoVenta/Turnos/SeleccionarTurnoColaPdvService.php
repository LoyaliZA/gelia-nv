<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use App\Services\PuntoVenta\Operacion\OperacionPdvConfig;

class SeleccionarTurnoColaPdvService
{
    public function __construct(
        private readonly OperacionPdvConfig $operacion,
    ) {}

    /**
     * Siguiente turno pendiente de asignación en la sucursal.
     *
     * Orden: accesibilidad y VIP, luego prioridad de lista y FIFO por alta_at.
     * ponytail: desempate entre turnos de igual prioridad usa alta_at; personas en Operación §5.
     */
    public function siguiente(int $sucursalId, string $servicio = TurnoPdv::SERVICIO_VENTAS): ?TurnoPdv
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('servicio', $servicio)
            ->where('estado', TurnoPdv::ESTADO_EN_COLA)
            ->whereDate('fecha_operativa', $this->operacion->fechaOperativa($sucursalId))
            ->whereNull('atencion_actual_id')
            ->enOrdenDeCola()
            ->lockForUpdate()
            ->first();
    }
}
