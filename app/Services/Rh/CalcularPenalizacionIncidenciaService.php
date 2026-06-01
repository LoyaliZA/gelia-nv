<?php

namespace App\Services\Rh;

use App\Models\CatalogoTipoFalta;
use App\Models\RhColaborador;

class CalcularPenalizacionIncidenciaService
{
    public function ejecutar(RhColaborador $colaborador, CatalogoTipoFalta $tipo, ?string $fechaDeduccionNomina = null): array
    {
        $deduccionSalario = $tipo->aplica_deduccion_salario_base
            ? round((float) $colaborador->salario_diario, 2)
            : 0.0;

        $deduccionPuntualidad = round(
            (float) $colaborador->bono_puntualidad_diario * (float) $tipo->factor_penalizacion_puntualidad,
            2,
        );

        $deduccionProductividad = round(
            (float) $colaborador->bono_productividad_diario * (float) $tipo->factor_penalizacion_productividad,
            2,
        );

        $totalDeduccion = (int) round($deduccionSalario + $deduccionPuntualidad + $deduccionProductividad);

        $estadoDeduccion = !empty($fechaDeduccionNomina) ? 'programado' : 'pendiente';

        return [
            'deduccion_salario_base' => $deduccionSalario,
            'deduccion_bono_puntualidad' => $deduccionPuntualidad,
            'deduccion_bono_productividad' => $deduccionProductividad,
            'total_deduccion' => $totalDeduccion,
            'estado_deduccion' => $estadoDeduccion,
            'salario_diario_snapshot' => $colaborador->salario_diario,
            'bono_puntualidad_diario_snapshot' => $colaborador->bono_puntualidad_diario,
            'bono_productividad_diario_snapshot' => $colaborador->bono_productividad_diario,
            'factor_puntualidad_snapshot' => $tipo->factor_penalizacion_puntualidad,
            'factor_productividad_snapshot' => $tipo->factor_penalizacion_productividad,
            'aplica_deduccion_salario_snapshot' => $tipo->aplica_deduccion_salario_base,
            'tipo_falta_nombre_snapshot' => $tipo->nombre,
        ];
    }
}
