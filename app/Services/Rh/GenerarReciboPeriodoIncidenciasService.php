<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhSalidaPersonal;
use App\Support\RhReciboAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Carbon\Carbon;

class GenerarReciboPeriodoIncidenciasService
{
    public function porColaborador(RhColaborador $colaborador, Carbon $fechaInicio, Carbon $fechaFin): DomPdfInstance
    {
        $colaborador->load(['departamento', 'area', 'puesto']);

        $incidencias = RhDeduccion::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereNotNull('catalogo_regla_incidencia_id')
            ->whereNull('rh_prestamo_pago_fijo_id')
            ->whereBetween('fecha_ocurrencia', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->with('reglaIncidencia')
            ->orderBy('fecha_ocurrencia')
            ->orderBy('id')
            ->get();

        $salidas = RhSalidaPersonal::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereBetween('fecha_evento', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->orderBy('fecha_evento')
            ->get();

        $totalIncidencias = (float) $incidencias->sum(fn (RhDeduccion $d) => $d->monto_total_final ?? $d->total_deduccion);
        $totalSalidas = (float) $salidas->sum('monto_a_deducir');

        return Pdf::loadView('rh.recibo_periodo_incidencias', [
            'colaborador' => $colaborador,
            'fechaInicio' => $fechaInicio->format('d/m/Y'),
            'fechaFin' => $fechaFin->format('d/m/Y'),
            'incidencias' => $incidencias,
            'salidas' => $salidas,
            'totalIncidencias' => round($totalIncidencias, 2),
            'totalSalidas' => round($totalSalidas, 2),
            'totalGeneral' => round($totalIncidencias + $totalSalidas, 2),
            'encabezado' => RhReciboAssets::encabezadoParaDepartamento($colaborador->departamento?->nombre, 'negro'),
        ])->setPaper('letter', 'portrait');
    }
}
