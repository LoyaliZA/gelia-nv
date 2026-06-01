<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhComisionAuditor;
use App\Models\RhConfiguracion;
use App\Models\RhDeduccion;
use App\Models\RhHorasExtra;
use App\Models\RhPrestamoPagoFijo;
use Carbon\Carbon;

class CalcularPeriodoPagoService
{
    public function ejecutar(Carbon $fechaInicio, Carbon $fechaFin, ?int $colaboradorId = null): array
    {
        $config = RhConfiguracion::obtener();
        $diasPeriodo = max(1, (int) $config->dias_periodo_pago);

        $colaboradores = RhColaborador::query()
            ->where('activo', true)
            ->when($colaboradorId, fn ($q) => $q->where('id', $colaboradorId))
            ->with(['departamento', 'area'])
            ->orderBy('nombre')
            ->get();

        $filas = [];

        foreach ($colaboradores as $colaborador) {
            $heQuery = RhHorasExtra::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereBetween('fecha_turno', [$fechaInicio->toDateString(), $fechaFin->toDateString()]);

            $dedQuery = RhDeduccion::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereBetween('fecha_ocurrencia', [$fechaInicio->toDateString(), $fechaFin->toDateString()]);

            $diasEnRango = $fechaInicio->diffInDays($fechaFin) + 1;
            $salarioDiario = (float) $colaborador->salario_diario;
            $bonoPuntDiario = (float) $colaborador->bono_puntualidad_diario;
            $bonoProdDiario = (float) $colaborador->bono_productividad_diario;

            $prestamosActivos = RhPrestamoPagoFijo::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->where('estado', RhPrestamoPagoFijo::ESTADO_ACTIVO)
                ->sum('monto_cuota');

            $filas[] = [
                'colaborador' => $colaborador,
                'salario_mensual' => round((float) $colaborador->salario_base, 2),
                'salario_diario' => $salarioDiario,
                'bono_puntualidad_mensual' => round((float) $colaborador->bono_puntualidad, 2),
                'bono_puntualidad_diario' => $bonoPuntDiario,
                'bono_productividad_mensual' => round((float) $colaborador->bono_productividad, 2),
                'bono_productividad_diario' => $bonoProdDiario,
                'dias_periodo_config' => $diasPeriodo,
                'dias_en_rango' => $diasEnRango,
                'salario_rango_estimado' => round($salarioDiario * $diasEnRango, 2),
                'bonos_rango_estimado' => round(($bonoPuntDiario + $bonoProdDiario) * $diasEnRango, 2),
                'horas_extra_total' => round((clone $heQuery)->sum('monto_horas_extra'), 2),
                'horas_extra_pendiente' => round((clone $heQuery)->where('estado_pago', 'pendiente')->sum('monto_horas_extra'), 2),
                'deducciones_total' => round((clone $dedQuery)->sum('monto_total_final'), 2),
                'deducciones_pendiente' => round((clone $dedQuery)->whereIn('estado_deduccion', [
                    RhDeduccion::ESTADO_PENDIENTE_NOMINA,
                    RhDeduccion::ESTADO_PENDIENTE_COMISION,
                ])->sum('monto_total_final'), 2),
                'prestamos_activos_cuota' => round((float) $prestamosActivos, 2),
                'neto_estimado' => round(
                    ($salarioDiario + $bonoPuntDiario + $bonoProdDiario) * $diasEnRango
                    + (clone $heQuery)->sum('monto_horas_extra')
                    - (clone $dedQuery)->sum('monto_total_final'),
                    2,
                ),
            ];
        }

        return [
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'dias_periodo_pago' => $diasPeriodo,
            'filas' => $filas,
        ];
    }

    public function comisionesAuditor(Carbon $fechaInicio, Carbon $fechaFin, ?int $userId = null): array
    {
        $query = RhComisionAuditor::query()
            ->with(['usuario', 'deduccion', 'reglaIncidencia'])
            ->whereBetween('fecha_acreditacion', [$fechaInicio->startOfDay(), $fechaFin->endOfDay()]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return [
            'total' => round((clone $query)->sum('monto'), 2),
            'pendiente' => round((clone $query)->where('estado', 'pendiente')->sum('monto'), 2),
            'registros' => $query->orderByDesc('fecha_acreditacion')->get(),
        ];
    }
}
