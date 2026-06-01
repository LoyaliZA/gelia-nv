<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhSalidaPersonal;
use Carbon\Carbon;

class CalcularConsolidadoDeduccionesService
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
            $prestamos = (float) RhDeduccion::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNotNull('rh_prestamo_pago_fijo_id')
                ->whereNull('fecha_deduccion_nomina')
                ->where('fecha_ocurrencia', '>=', $fechaInicio->toDateString())
                ->where('fecha_ocurrencia', '<=', $fechaFin->toDateString())
                ->sum('monto_total_final');

            $incidencias = (float) RhDeduccion::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNull('rh_prestamo_pago_fijo_id')
                ->whereHas('reglaIncidencia', function ($q) {
                    $q->where('categoria', 'operativa');
                })
                ->whereNull('fecha_deduccion_nomina')
                ->where('fecha_ocurrencia', '>=', $fechaInicio->toDateString())
                ->where('fecha_ocurrencia', '<=', $fechaFin->toDateString())
                ->sum('monto_total_final');

            $faltasSalario = (float) RhDeduccion::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNull('rh_prestamo_pago_fijo_id')
                ->whereHas('reglaIncidencia', function ($q) {
                    $q->whereIn('categoria', ['falta', 'retardo']);
                })
                ->whereNull('fecha_deduccion_nomina')
                ->where('fecha_ocurrencia', '>=', $fechaInicio->toDateString())
                ->where('fecha_ocurrencia', '<=', $fechaFin->toDateString())
                ->sum('deduccion_salario_base');

            $faltasProductividad = (float) RhDeduccion::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNull('rh_prestamo_pago_fijo_id')
                ->whereHas('reglaIncidencia', function ($q) {
                    $q->whereIn('categoria', ['falta', 'retardo']);
                })
                ->whereNull('fecha_deduccion_nomina')
                ->where('fecha_ocurrencia', '>=', $fechaInicio->toDateString())
                ->where('fecha_ocurrencia', '<=', $fechaFin->toDateString())
                ->sum('deduccion_bono_productividad');

            $faltasPuntualidad = (float) RhDeduccion::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNull('rh_prestamo_pago_fijo_id')
                ->whereHas('reglaIncidencia', function ($q) {
                    $q->whereIn('categoria', ['falta', 'retardo']);
                })
                ->whereNull('fecha_deduccion_nomina')
                ->where('fecha_ocurrencia', '>=', $fechaInicio->toDateString())
                ->where('fecha_ocurrencia', '<=', $fechaFin->toDateString())
                ->sum('deduccion_bono_puntualidad');

            $salidasPersonales = (float) RhSalidaPersonal::query()
                ->where('rh_colaborador_id', $colaborador->id)
                ->whereNull('fecha_deduccion_nomina')
                ->where('fecha_evento', '>=', $fechaInicio->toDateString())
                ->where('fecha_evento', '<=', $fechaFin->toDateString())
                ->sum('monto_a_deducir');

            $granTotal = $prestamos + $incidencias + $faltasSalario + $faltasProductividad + $faltasPuntualidad + $salidasPersonales;
            $sinIncidencias = $granTotal - $incidencias;

            $filas[] = [
                'colaborador' => $colaborador,
                'prestamos' => round($prestamos, 2),
                'incidencias' => round($incidencias, 2),
                'faltas_salario' => round($faltasSalario, 2),
                'faltas_productividad' => round($faltasProductividad, 2),
                'faltas_puntualidad' => round($faltasPuntualidad, 2),
                'salidas_personales' => round($salidasPersonales, 2),
                'gran_total' => round($granTotal, 2),
                'sin_incidencias' => round($sinIncidencias, 2),
            ];
        }

        return [
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'filas' => $filas,
        ];
    }
}
