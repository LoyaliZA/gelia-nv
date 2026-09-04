<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;

class SeleccionarTurnoColaPdvService
{
    /**
     * Siguiente turno pendiente de asignación en la sucursal.
     *
     * Orden: prioridad al frente (contrato §6) y FIFO por alta_at dentro de cada nivel.
     * ponytail: desempate entre turnos de igual prioridad usa alta_at; personas en Operación §5.
     */
    public function siguiente(int $sucursalId, string $servicio = TurnoPdv::SERVICIO_VENTAS): ?TurnoPdv
    {
        $ahora = now();

        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('servicio', $servicio)
            ->where(function ($query) use ($ahora) {
                $query->where('estado', TurnoPdv::ESTADO_EN_COLA)
                    ->orWhere(function ($query) use ($ahora) {
                        $query->where('estado', TurnoPdv::ESTADO_EN_REATENCION)
                            ->where('reatencion_expira_at', '>', $ahora);
                    });
            })
            ->whereNull('atencion_actual_id')
            ->orderByRaw(
                'CASE WHEN prioridad_adulto_mayor = 1'
                .' OR prioridad_discapacidad = 1'
                .' OR prioridad_diamante = 1'
                .' OR prioridad_vip = 1 THEN 0 ELSE 1 END ASC'
            )
            ->orderBy('alta_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
