<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhConfiguracion;

class CalcularSalariosColaboradorService
{
    public function ejecutar(RhColaborador $colaborador, ?RhConfiguracion $config = null): RhColaborador
    {
        $config = $config ?? RhConfiguracion::obtener();

        $dias = max(1, (int) $config->dias_periodo_pago);
        $horas = max(0.01, (float) $colaborador->horas_laboradas_oficiales);

        $salarioBase = (float) $colaborador->salario_base;
        $bonoProd = (float) $colaborador->bono_productividad;
        $bonoPunt = (float) $colaborador->bono_puntualidad;

        $salarioDiario = round($salarioBase / $dias, 2);
        $bonoProdDiario = round($bonoProd / $dias, 2);
        $bonoPuntDiario = round($bonoPunt / $dias, 2);
        $salarioPorHora = round($salarioDiario / $horas, 4);
        $salarioPorMinuto = round($salarioDiario / ($horas * 60), $config->decimales_salario_minuto);

        $colaborador->salario_diario = $salarioDiario;
        $colaborador->bono_productividad_diario = $bonoProdDiario;
        $colaborador->bono_puntualidad_diario = $bonoPuntDiario;
        $colaborador->salario_por_hora = $salarioPorHora;
        $colaborador->salario_por_minuto = $salarioPorMinuto;

        return $colaborador;
    }

    /**
     * @return array<string, float|string>
     */
    public function preview(array $datos, ?RhConfiguracion $config = null): array
    {
        $config = $config ?? RhConfiguracion::obtener();

        $dias = max(1, (int) $config->dias_periodo_pago);
        $horas = max(0.01, (float) ($datos['horas_laboradas_oficiales'] ?? 8));

        $salarioBase = (float) ($datos['salario_base'] ?? 0);
        $bonoProd = (float) ($datos['bono_productividad'] ?? 0);
        $bonoPunt = (float) ($datos['bono_puntualidad'] ?? 0);

        $salarioDiario = round($salarioBase / $dias, 2);

        return [
            'salario_diario' => $salarioDiario,
            'bono_productividad_diario' => round($bonoProd / $dias, 2),
            'bono_puntualidad_diario' => round($bonoPunt / $dias, 2),
            'salario_por_hora' => round($salarioDiario / $horas, 4),
            'salario_por_minuto' => round($salarioDiario / ($horas * 60), $config->decimales_salario_minuto),
        ];
    }
}
