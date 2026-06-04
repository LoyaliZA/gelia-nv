<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellarConsolidadoDeduccionesService
{
    public function ejecutar(Carbon $fechaInicio, Carbon $fechaFin, $usuario): int
    {
        $diasEnRango = $fechaInicio->diffInDays($fechaFin) + 1;
        $afectados = 0;

        DB::transaction(function () use ($fechaInicio, $fechaFin, $diasEnRango, $usuario, &$afectados) {
            $colaboradoresIds = RhDeduccion::query()
                ->whereNull('fecha_deduccion_nomina')
                ->whereBetween('fecha_ocurrencia', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
                ->select('rh_colaborador_id')
                ->distinct()
                ->pluck('rh_colaborador_id');

            foreach ($colaboradoresIds as $colaboradorId) {
                $colaborador = RhColaborador::find($colaboradorId);
                if (!$colaborador) {
                    continue;
                }

                // 1. Obtener deducciones de puntualidad del colaborador en el periodo
                $deduccionesPuntualidad = RhDeduccion::query()
                    ->where('rh_colaborador_id', $colaborador->id)
                    ->whereNull('fecha_deduccion_nomina')
                    ->whereHas('reglaIncidencia', function ($q) {
                        $q->whereIn('categoria', ['falta', 'retardo']);
                    })
                    ->whereBetween('fecha_ocurrencia', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
                    ->get();

                $totalPuntualidad = $deduccionesPuntualidad->sum('deduccion_bono_puntualidad');
                $bonoPuntualidadMaximo = round((float) $colaborador->bono_puntualidad_diario * $diasEnRango, 2);

                if ($totalPuntualidad > $bonoPuntualidadMaximo && $bonoPuntualidadMaximo > 0) {
                    // El usuario perdió más bono de puntualidad del que le correspondía
                    // 1. Topar el último registro o distribuirlo para que la suma sea exactamente $bonoPuntualidadMaximo
                    $acumulado = 0;
                    foreach ($deduccionesPuntualidad as $deduccion) {
                        if ($acumulado + $deduccion->deduccion_bono_puntualidad > $bonoPuntualidadMaximo) {
                            // Esta deducción se pasa del límite, la ajustamos
                            $nuevoMontoPunt = $bonoPuntualidadMaximo - $acumulado;
                            $diferencia = $deduccion->deduccion_bono_puntualidad - $nuevoMontoPunt;
                            
                            $deduccion->update([
                                'deduccion_bono_puntualidad' => $nuevoMontoPunt,
                                'monto_total_final' => $deduccion->monto_total_final - $diferencia,
                                'total_deduccion' => (int) round($deduccion->monto_total_final - $diferencia),
                            ]);
                            $acumulado += $nuevoMontoPunt;
                        } else {
                            $acumulado += $deduccion->deduccion_bono_puntualidad;
                        }
                    }

                    // 2. Crear un nuevo registro de arrastre por la diferencia
                    $exceso = $totalPuntualidad - $bonoPuntualidadMaximo;
                    $reglaId = $deduccionesPuntualidad->first()->catalogo_regla_incidencia_id;

                    RhDeduccion::create([
                        'uuid' => Str::uuid(),
                        'folio' => 'ARR-' . strtoupper(Str::random(6)),
                        'fecha_ocurrencia' => $fechaFin->copy()->addDay()->toDateString(),
                        'rh_colaborador_id' => $colaborador->id,
                        'catalogo_regla_incidencia_id' => $reglaId,
                        'monto_deduccion_base' => $exceso,
                        'factor_multiplicador' => 1,
                        'total_parcial' => $exceso,
                        'monto_total_final' => $exceso,
                        'deduccion_salario_base' => 0,
                        'deduccion_bono_puntualidad' => $exceso,
                        'deduccion_bono_productividad' => 0,
                        'total_deduccion' => (int) round($exceso),
                        'origen_deduccion' => RhDeduccion::ORIGEN_NOMINA,
                        'descripcion_detallada' => "Arrastre de penalización de puntualidad del periodo {$fechaInicio->toDateString()} al {$fechaFin->toDateString()}.",
                        'fecha_deduccion_nomina' => null, // Queda pendiente para el SIGUIENTE periodo
                        'estado_deduccion' => RhDeduccion::ESTADO_PENDIENTE_NOMINA,
                        'registrado_por_id' => $usuario->id,
                        
                        // Snapshots requeridos
                        'salario_diario_snapshot' => $colaborador->salario_diario,
                        'bono_puntualidad_diario_snapshot' => $colaborador->bono_puntualidad_diario,
                        'bono_productividad_diario_snapshot' => $colaborador->bono_productividad_diario,
                        'factor_puntualidad_snapshot' => 1,
                        'factor_productividad_snapshot' => 0,
                        'aplica_deduccion_salario_snapshot' => false,
                        'regla_nombre_snapshot' => 'Arrastre de Penalización de Puntualidad',
                        'regla_comportamiento_snapshot' => 'deduccion_nomina',
                        'departamento_snapshot' => $colaborador->departamento?->nombre,
                        'area_snapshot' => $colaborador->area?->nombre,
                    ]);
                }

                // 3. Marcar todas las deducciones del periodo (incluyendo las recién ajustadas) como cobradas/aplicadas
                $afectados += RhDeduccion::query()
                    ->where('rh_colaborador_id', $colaborador->id)
                    ->whereNull('fecha_deduccion_nomina')
                    ->whereBetween('fecha_ocurrencia', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
                    ->update([
                        'fecha_deduccion_nomina' => now()->toDateString(),
                        'fecha_aplicacion_deduccion' => now()->toDateString(),
                        'estado_deduccion' => RhDeduccion::ESTADO_APLICADO,
                    ]);
            }
        });

        return $afectados;
    }
}
