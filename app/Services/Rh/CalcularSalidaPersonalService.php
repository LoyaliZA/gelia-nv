<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use Carbon\Carbon;

class CalcularSalidaPersonalService
{
    /**
     * @return array{
     *   minutos_ausente: int,
     *   monto_a_deducir: int,
     *   salario_por_minuto_snapshot: float,
     * }
     */
    public function ejecutar(array $datos, ?RhColaborador $colaborador = null): array
    {
        $colaborador = $colaborador ?? RhColaborador::findOrFail($datos['rh_colaborador_id']);
        $salarioPorMinuto = (float) ($colaborador->salario_por_minuto ?? 0);

        if (empty($datos['hora_salida']) || empty($datos['hora_regreso'])) {
            return [
                'minutos_ausente' => 0,
                'monto_a_deducir' => 0,
                'salario_por_minuto_snapshot' => $salarioPorMinuto,
            ];
        }

        $fechaEvento = Carbon::parse($datos['fecha_evento'] ?? now()->toDateString());
        $horaSalidaStr = $this->parseTime($datos['hora_salida']);
        $horaRegresoStr = $this->parseTime($datos['hora_regreso']);

        $salida = $fechaEvento->copy()->setTimeFromTimeString($horaSalidaStr);
        
        $regresoDiaSiguiente = false;
        if ($horaRegresoStr < $horaSalidaStr) {
            $regresoDiaSiguiente = true;
        }

        $fechaRegreso = $regresoDiaSiguiente ? $fechaEvento->copy()->addDay() : $fechaEvento->copy();
        $regreso = $fechaRegreso->setTimeFromTimeString($horaRegresoStr);

        $minutosAusente = (int) max(0, $salida->diffInMinutes($regreso));
        
        // El resultado final se redondea a una cantidad entera cerrada
        $montoADeducir = (int) round($minutosAusente * $salarioPorMinuto);

        return [
            'minutos_ausente' => $minutosAusente,
            'monto_a_deducir' => $montoADeducir,
            'salario_por_minuto_snapshot' => $salarioPorMinuto,
        ];
    }

    private function parseTime(string $time): string
    {
        if (strlen($time) === 5) {
            return $time . ':00';
        }
        return $time;
    }
}
