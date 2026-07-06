<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhReciboNomina;
use App\Support\RhReciboAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class GenerarReciboNominaService
{
    public function __construct(
        private CalcularPeriodoPagoService $calcularPeriodoPago,
        private DesgloseReciboNominaService $desgloseService,
    ) {}

    public function porColaborador(
        RhColaborador $colaborador,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        string $orientacion = 'portrait',
    ): DomPdfInstance
    {
        $colaborador->load(['departamento', 'area', 'puesto']);

        $inicio = $fechaInicio->toDateString();
        $fin = $fechaFin->toDateString();
        $diasEnRango = $fechaInicio->diffInDays($fechaFin) + 1;

        $resumen = $this->calcularPeriodoPago->ejecutar($fechaInicio, $fechaFin, $colaborador->id);
        $fila = $resumen['filas'][0] ?? null;

        $salarioDiario = (float) $colaborador->salario_diario;
        $bonoPuntDiario = (float) $colaborador->bono_puntualidad_diario;
        $bonoProdDiario = (float) $colaborador->bono_productividad_diario;

        $desglose = $this->desgloseService->ejecutar($colaborador, $fechaInicio, $fechaFin);
        $movimientos = $this->desgloseService->movimientosDesdeDesglose($desglose);
        $totales = $desglose['totales'];

        $totalPrestamos = $totales['prestamos'];
        $totalIncidencias = $totales['incidencias'];
        $totalFaltasSalario = $totales['faltas_salario'];
        $totalFaltasPuntualidad = $totales['faltas_puntualidad'];
        $totalFaltasProductividad = $totales['faltas_productividad'];
        $totalSalidas = $totales['salidas'];
        $totalHorasExtra = $totales['horas_extra'];

        $salarioRango = round($salarioDiario * $diasEnRango, 2);
        $bonoPuntRango = round($bonoPuntDiario * $diasEnRango, 2);
        $bonoProdRango = round($bonoProdDiario * $diasEnRango, 2);
        $totalPercepciones = round($salarioRango + $bonoPuntRango + $bonoProdRango + $totalHorasExtra, 2);
        $totalDeducciones = round($totalPrestamos + $totalIncidencias + $totalFaltasSalario + $totalFaltasPuntualidad + $totalFaltasProductividad + $totalSalidas, 2);
        $neto = round($totalPercepciones - $totalDeducciones, 2);

        $orientacion = in_array($orientacion, ['portrait', 'landscape'], true) ? $orientacion : 'portrait';
        $esHorizontal = $orientacion === 'landscape';

        $totalMovimientos = $movimientos->count();
        $modoUltraCompacto = $totalMovimientos > ($esHorizontal ? 20 : 14);
        $maxFilasDetalle = $modoUltraCompacto
            ? ($esHorizontal ? 36 : 24)
            : ($esHorizontal ? 48 : 36);
        $movimientosOmitidos = max(0, $totalMovimientos - $maxFilasDetalle);
        $movimientosVisibles = $movimientos->take($maxFilasDetalle)->values();

        $columnasDetalle = 1;
        if ($movimientosVisibles->count() > 4) {
            $columnasDetalle = $esHorizontal ? 3 : ($movimientosVisibles->count() > 10 ? 2 : 1);
        }

        $porColumna = $columnasDetalle > 1
            ? (int) ceil($movimientosVisibles->count() / $columnasDetalle)
            : $movimientosVisibles->count();
        $movimientosColumnas = [];
        for ($i = 0; $i < $columnasDetalle; $i++) {
            $movimientosColumnas[] = $movimientosVisibles->slice($i * $porColumna, $porColumna)->values();
        }

        $reciboFirmado = RhReciboNomina::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->where('fecha_inicio', $inicio)
            ->where('fecha_fin', $fin)
            ->first();

        $firmaColaboradorBase64 = null;
        if ($reciboFirmado?->firma_colaborador_path
            && Storage::disk('public')->exists($reciboFirmado->firma_colaborador_path)) {
            $firmaColaboradorBase64 = base64_encode(
                Storage::disk('public')->get($reciboFirmado->firma_colaborador_path),
            );
        }

        $pdf = Pdf::loadView('rh.recibo_nomina', [
            'colaborador' => $colaborador,
            'fechaInicio' => $fechaInicio->format('d/m/Y'),
            'fechaFin' => $fechaFin->format('d/m/Y'),
            'diasEnRango' => $diasEnRango,
            'nominaBase' => [
                'salario_mensual' => $fila['salario_mensual'] ?? round((float) $colaborador->salario_base, 2),
                'salario_diario' => $salarioDiario,
                'bono_puntualidad_mensual' => $fila['bono_puntualidad_mensual'] ?? round((float) $colaborador->bono_puntualidad, 2),
                'bono_productividad_mensual' => $fila['bono_productividad_mensual'] ?? round((float) $colaborador->bono_productividad, 2),
                'bono_puntualidad_diario' => $bonoPuntDiario,
                'bono_productividad_diario' => $bonoProdDiario,
            ],
            'percepciones' => [
                'salario_rango' => $salarioRango,
                'bono_puntualidad_rango' => $bonoPuntRango,
                'bono_productividad_rango' => $bonoProdRango,
                'horas_extra' => $totalHorasExtra,
                'total' => $totalPercepciones,
            ],
            'deducciones' => [
                'totales' => [
                    'prestamos' => $totalPrestamos,
                    'incidencias' => $totalIncidencias,
                    'faltas_salario' => $totalFaltasSalario,
                    'faltas_puntualidad' => $totalFaltasPuntualidad,
                    'faltas_productividad' => $totalFaltasProductividad,
                    'salidas' => $totalSalidas,
                    'total' => $totalDeducciones,
                ],
            ],
            'neto' => $neto,
            'movimientos' => $movimientosVisibles,
            'movimientosColumnas' => $movimientosColumnas,
            'columnasDetalle' => $columnasDetalle,
            'movimientosOmitidos' => $movimientosOmitidos,
            'modoUltraCompacto' => $modoUltraCompacto,
            'orientacion' => $orientacion,
            'esHorizontal' => $esHorizontal,
            'layoutExpandido' => $totalMovimientos <= 12 && ! $modoUltraCompacto,
            'firmaColaboradorBase64' => $firmaColaboradorBase64,
            'encabezado' => RhReciboAssets::encabezadoParaDepartamento($colaborador->departamento?->nombre, 'negro'),
        ])->setPaper('letter', $orientacion);

        return $pdf;
    }
}
