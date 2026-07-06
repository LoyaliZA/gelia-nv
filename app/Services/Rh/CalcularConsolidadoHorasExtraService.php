<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhHorasExtra;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
            $enPeriodo = RhHorasExtra::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereDate('fecha_turno', '>=', $fechaInicio->toDateString())
                ->whereDate('fecha_turno', '<=', $fechaFin->toDateString());

            $pendientes = (clone $enPeriodo)->where('estado_pago', 'pendiente');

            $horasAcumuladas = (float) (clone $pendientes)->sum('horas_extra_a_pagar');

            $totalEconomico = (float) (clone $pendientes)->sum(
                DB::raw('COALESCE(NULLIF(monto_horas_extra, 0), NULLIF(total_economico, 0), 0)')
            );

            $horasPeriodo = (float) (clone $enPeriodo)->sum('horas_extra_a_pagar');

            $totalPeriodo = (float) (clone $enPeriodo)->sum(
                DB::raw('COALESCE(NULLIF(monto_horas_extra, 0), NULLIF(total_economico, 0), 0)')
            );

            $detalle = (clone $enPeriodo)
                ->orderBy('fecha_turno')
                ->orderBy('id')
                ->get(['id', 'folio', 'fecha_turno', 'horas_extra_a_pagar', 'total_economico', 'monto_horas_extra', 'estado_pago', 'fecha_programada_pago'])
                ->map(fn ($registro) => [
                    'id' => $registro->id,
                    'folio' => $registro->folio,
                    'fecha_turno' => $registro->fecha_turno?->toDateString(),
                    'horas_extra_a_pagar' => (int) $registro->horas_extra_a_pagar,
                    'monto' => round((float) ($registro->monto_horas_extra ?: $registro->total_economico), 2),
                    'estado_pago' => $registro->estado_pago,
                    'fecha_programada_pago' => $registro->fecha_programada_pago?->toDateString(),
                ])
                ->values()
                ->all();

            $filas[] = [
                'colaborador' => $colaborador,
                'horas_extra_acumuladas' => round($horasAcumuladas, 2),
                'total_economico_acumulado' => round($totalEconomico, 2),
                'horas_periodo_total' => round($horasPeriodo, 2),
                'total_periodo' => round($totalPeriodo, 2),
                'detalle' => $detalle,
            ];
        }

        return [
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'filas' => $filas,
        ];
    }
}
