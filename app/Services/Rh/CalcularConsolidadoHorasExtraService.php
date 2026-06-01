<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhHorasExtra;
use Carbon\Carbon;

class CalcularConsolidadoHorasExtraService
{
    public function ejecutar(Carbon $fechaInicio, Carbon $fechaFin, ?int $colaboradorId = null): array
    {
        $colaboradores = RhColaborador::query()
            ->where('activo', true)
            ->when($colaboradorId, fn ($q) => $q->where('id', $colaboradorId))
            ->with(['departamento', 'area'])
            ->orderBy('nombre')
            ->get();

        $filas = [];

        foreach ($colaboradores as $colaborador) {
            $horasAcumuladas = (float) RhHorasExtra::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNull('fecha_programada_pago')
                ->where('fecha_turno', '>=', $fechaInicio->toDateString())
                ->where('fecha_turno', '<=', $fechaFin->toDateString())
                ->sum('horas_extra_a_pagar');

            $totalEconomico = (float) RhHorasExtra::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNull('fecha_programada_pago')
                ->where('fecha_turno', '>=', $fechaInicio->toDateString())
                ->where('fecha_turno', '<=', $fechaFin->toDateString())
                ->sum('total_economico');

            $filas[] = [
                'colaborador' => $colaborador,
                'horas_extra_acumuladas' => round($horasAcumuladas, 2),
                'total_economico_acumulado' => round($totalEconomico, 2),
            ];
        }

        return [
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'filas' => $filas,
        ];
    }
}
