<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhHorasExtra;
use App\Models\RhPrestamoPagoFijo;
use App\Models\RhSalidaPersonal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DesgloseReciboNominaService
{
    /**
     * @return array{
     *     horas_extra: list<array{fecha: string, folio: string, concepto: string, monto: float, detalle: string}>,
     *     incidencias: list<array{fecha: string, folio: string, concepto: string, monto: float, tipo: string}>,
     *     faltas_retardos: list<array{fecha: string, folio: string, concepto: string, monto: float, tipo: string}>,
     *     prestamos_periodo: list<array{fecha: string, folio: string, concepto: string, monto: float}>,
     *     salidas: list<array{fecha: string, folio: string, concepto: string, monto: float, minutos: int|null}>,
     *     prestamos_activos: list<array{folio: string, concepto: string, monto: float, pagos: string}>,
     * }
     */
    public function ejecutar(RhColaborador $colaborador, Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $inicio = $fechaInicio->toDateString();
        $fin = $fechaFin->toDateString();

        $horasExtra = RhHorasExtra::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereBetween('fecha_turno', [$inicio, $fin])
            ->orderBy('fecha_turno')
            ->orderBy('id')
            ->get();

        $prestamosPeriodo = RhDeduccion::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereNotNull('rh_prestamo_pago_fijo_id')
            ->whereBetween('fecha_ocurrencia', [$inicio, $fin])
            ->with('prestamoPagoFijo')
            ->orderBy('fecha_ocurrencia')
            ->get();

        $incidencias = RhDeduccion::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereNull('rh_prestamo_pago_fijo_id')
            ->whereHas('reglaIncidencia', fn ($q) => $q->where('categoria', 'operativa'))
            ->whereBetween('fecha_ocurrencia', [$inicio, $fin])
            ->with('reglaIncidencia')
            ->orderBy('fecha_ocurrencia')
            ->get();

        $faltasRetardos = RhDeduccion::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereNull('rh_prestamo_pago_fijo_id')
            ->whereHas('reglaIncidencia', fn ($q) => $q->whereIn('categoria', ['falta', 'retardo']))
            ->whereBetween('fecha_ocurrencia', [$inicio, $fin])
            ->with('reglaIncidencia')
            ->orderBy('fecha_ocurrencia')
            ->get();

        $salidas = RhSalidaPersonal::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->whereBetween('fecha_evento', [$inicio, $fin])
            ->orderBy('fecha_evento')
            ->get();

        $prestamosActivos = RhPrestamoPagoFijo::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->where('estado', RhPrestamoPagoFijo::ESTADO_ACTIVO)
            ->orderBy('folio')
            ->get();

        $totalPrestamos = round((float) $prestamosPeriodo->sum(fn (RhDeduccion $d) => $d->monto_total_final ?? $d->total_deduccion), 2);
        $totalIncidencias = round((float) $incidencias->sum(fn (RhDeduccion $d) => $d->monto_total_final ?? $d->total_deduccion), 2);
        $totalFaltasSalario = round((float) $faltasRetardos->sum('deduccion_salario_base'), 2);
        $totalFaltasPuntualidad = round((float) $faltasRetardos->sum('deduccion_bono_puntualidad'), 2);
        $totalFaltasProductividad = round((float) $faltasRetardos->sum('deduccion_bono_productividad'), 2);
        $totalSalidas = round((float) $salidas->sum('monto_a_deducir'), 2);
        $totalHorasExtra = round((float) $horasExtra->sum('monto_horas_extra'), 2);

        return [
            'horas_extra' => $horasExtra->map(fn (RhHorasExtra $he) => [
                'fecha' => $he->fecha_turno?->toDateString() ?? '',
                'folio' => $he->folio,
                'concepto' => 'Horas extra',
                'detalle' => sprintf(
                    '%s min · %s h a pagar · %s',
                    $he->tiempo_extra_minutos ?? '—',
                    $he->horas_extra_a_pagar ?? '—',
                    $he->estado_pago === 'pagado' ? 'Pagado' : 'Pendiente',
                ),
                'monto' => round((float) $he->monto_horas_extra, 2),
            ])->values()->all(),
            'incidencias' => $incidencias->map(fn (RhDeduccion $ded) => [
                'fecha' => $ded->fecha_ocurrencia?->toDateString() ?? '',
                'folio' => $ded->folio,
                'concepto' => $ded->regla_nombre_snapshot ?? $ded->reglaIncidencia?->nombre ?? 'Incidencia',
                'tipo' => 'Incidencia',
                'monto' => round((float) ($ded->monto_total_final ?? $ded->total_deduccion), 2),
            ])->values()->all(),
            'faltas_retardos' => $faltasRetardos->map(fn (RhDeduccion $ded) => [
                'fecha' => $ded->fecha_ocurrencia?->toDateString() ?? '',
                'folio' => $ded->folio,
                'concepto' => $ded->regla_nombre_snapshot ?? $ded->reglaIncidencia?->nombre ?? 'Falta / retardo',
                'tipo' => 'Falta / retardo',
                'monto' => round((float) ($ded->monto_total_final ?? $ded->total_deduccion), 2),
            ])->values()->all(),
            'prestamos_periodo' => $prestamosPeriodo->map(fn (RhDeduccion $ded) => [
                'fecha' => $ded->fecha_ocurrencia?->toDateString() ?? '',
                'folio' => $ded->folio,
                'concepto' => $ded->regla_nombre_snapshot
                    ?? ($ded->prestamoPagoFijo ? 'Cuota '.$ded->prestamoPagoFijo->folio : 'Cuota préstamo'),
                'monto' => round((float) ($ded->monto_total_final ?? $ded->total_deduccion), 2),
            ])->values()->all(),
            'salidas' => $salidas->map(fn (RhSalidaPersonal $sal) => [
                'fecha' => $sal->fecha_evento?->toDateString() ?? '',
                'folio' => $sal->folio,
                'concepto' => $sal->motivo ?: 'Salida personal',
                'minutos' => $sal->minutos_ausente,
                'monto' => round((float) $sal->monto_a_deducir, 2),
            ])->values()->all(),
            'prestamos_activos' => $prestamosActivos->map(fn (RhPrestamoPagoFijo $p) => [
                'folio' => $p->folio,
                'concepto' => $p->concepto ?: 'Préstamo activo',
                'monto' => round((float) $p->monto_cuota, 2),
                'pagos' => sprintf('%d / %s', $p->pagos_realizados ?? 0, $p->num_pagos_total ?? '—'),
            ])->values()->all(),
            'totales' => [
                'prestamos' => $totalPrestamos,
                'incidencias' => $totalIncidencias,
                'faltas_salario' => $totalFaltasSalario,
                'faltas_puntualidad' => $totalFaltasPuntualidad,
                'faltas_productividad' => $totalFaltasProductividad,
                'salidas' => $totalSalidas,
                'horas_extra' => $totalHorasExtra,
                'deducciones' => round($totalPrestamos + $totalIncidencias + $totalFaltasSalario + $totalFaltasPuntualidad + $totalFaltasProductividad + $totalSalidas, 2),
            ],
        ];
    }

    /** Movimientos unificados para el PDF del recibo. */
    public function movimientosParaPdf(RhColaborador $colaborador, Carbon $fechaInicio, Carbon $fechaFin): Collection
    {
        return $this->movimientosDesdeDesglose(
            $this->ejecutar($colaborador, $fechaInicio, $fechaFin),
        );
    }

    /** @param  array<string, mixed>  $desglose */
    public function movimientosDesdeDesglose(array $desglose): Collection
    {
        $movimientos = collect();

        foreach ($desglose['horas_extra'] as $item) {
            $movimientos->push([
                'tipo' => 'HE',
                'fecha' => $this->formatearFechaCorta($item['fecha']),
                'ref' => $item['folio'],
                'concepto' => $item['concepto'].' · '.explode(' · ', $item['detalle'])[0],
                'monto' => $item['monto'],
                'es_percepcion' => true,
            ]);
        }

        foreach ($desglose['prestamos_periodo'] as $item) {
            $movimientos->push([
                'tipo' => 'Préstamo',
                'fecha' => $this->formatearFechaCorta($item['fecha']),
                'ref' => $item['folio'],
                'concepto' => $item['concepto'],
                'monto' => $item['monto'],
                'es_percepcion' => false,
            ]);
        }

        foreach ($desglose['incidencias'] as $item) {
            $movimientos->push([
                'tipo' => 'Incidencia',
                'fecha' => $this->formatearFechaCorta($item['fecha']),
                'ref' => $item['folio'],
                'concepto' => $item['concepto'],
                'monto' => $item['monto'],
                'es_percepcion' => false,
            ]);
        }

        foreach ($desglose['faltas_retardos'] as $item) {
            $movimientos->push([
                'tipo' => 'Falta',
                'fecha' => $this->formatearFechaCorta($item['fecha']),
                'ref' => $item['folio'],
                'concepto' => $item['concepto'],
                'monto' => $item['monto'],
                'es_percepcion' => false,
            ]);
        }

        foreach ($desglose['salidas'] as $item) {
            $movimientos->push([
                'tipo' => 'Salida',
                'fecha' => $this->formatearFechaCorta($item['fecha']),
                'ref' => $item['folio'],
                'concepto' => $item['concepto'],
                'monto' => $item['monto'],
                'es_percepcion' => false,
            ]);
        }

        return $movimientos;
    }

    private function formatearFechaCorta(string $fecha): string
    {
        if ($fecha === '') {
            return '—';
        }

        return Carbon::parse($fecha)->format('d/m');
    }
}
