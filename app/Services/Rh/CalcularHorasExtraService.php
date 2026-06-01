<?php

namespace App\Services\Rh;

use App\Models\RhConfiguracion;
use App\Models\RhHorasExtra;
use Carbon\Carbon;

class CalcularHorasExtraService
{
    /**
     * @return array{
     *   salida_dia_siguiente: bool,
     *   total_horas_laboradas: float,
     *   horas_normales_snapshot: float,
     *   tiempo_extra_crudo: float,
     *   tiempo_extra_minutos: int,
     *   horas_extra_a_pagar: int,
     *   salario_por_hora_snapshot: float,
     *   multiplicador_snapshot: float,
     *   total_economico: float,
     *   estado_pago: string,
     *   area_snapshot: ?string,
     * }
     */
    public function ejecutar(array $datos, ?RhConfiguracion $config = null): array
    {
        $config = $config ?? RhConfiguracion::obtener();

        $fechaTurno = Carbon::parse($datos['fecha_turno']);
        $horaEntrada = $this->parseTime($datos['hora_entrada']);
        $horaSalida = $this->parseTime($datos['hora_salida']);

        $salidaDiaSiguiente = false;
        if (array_key_exists('salida_dia_siguiente', $datos)) {
            $salidaDiaSiguiente = (bool) $datos['salida_dia_siguiente'];
        } elseif ($horaSalida <= $horaEntrada) {
            $salidaDiaSiguiente = true;
        }

        $entrada = $fechaTurno->copy()->setTimeFromTimeString($horaEntrada);
        $fechaSalida = $salidaDiaSiguiente ? $fechaTurno->copy()->addDay() : $fechaTurno->copy();
        $salida = $fechaSalida->setTimeFromTimeString($horaSalida);

        $totalMinutos = max(0, $entrada->diffInMinutes($salida));
        $totalHoras = round($totalMinutos / 60, 2);

        $horasNormales = (float) ($datos['horas_normales_snapshot'] ?? 8);
        $tiempoExtraCrudo = max(0, round($totalHoras - $horasNormales, 2));
        $tiempoExtraMinutos = (int) round($tiempoExtraCrudo * 60);

        $minutosMinimos = max(1, (int) $config->he_minutos_minimos);
        $horasExtraAPagar = $this->calcularHorasAPagar($tiempoExtraMinutos, $minutosMinimos);

        $salarioPorHora = (float) ($datos['salario_por_hora_snapshot'] ?? 0);
        $multiplicador = (float) ($datos['multiplicador_snapshot'] ?? $config->he_multiplicador_pago ?? 2);
        $totalEconomico = round($horasExtraAPagar * $multiplicador * $salarioPorHora, 2);

        $fechaProgramada = $datos['fecha_programada_pago'] ?? null;
        $estadoPago = $fechaProgramada ? 'programado' : 'pendiente';

        return [
            'salida_dia_siguiente' => $salidaDiaSiguiente,
            'total_horas_laboradas' => $totalHoras,
            'horas_normales_snapshot' => $horasNormales,
            'tiempo_extra_crudo' => $tiempoExtraCrudo,
            'tiempo_extra_minutos' => $tiempoExtraMinutos,
            'horas_extra_a_pagar' => $horasExtraAPagar,
            'salario_por_hora_snapshot' => $salarioPorHora,
            'multiplicador_snapshot' => $multiplicador,
            'total_economico' => $totalEconomico,
            'estado_pago' => $estadoPago,
            'area_snapshot' => $datos['area_snapshot'] ?? null,
        ];
    }

    public function calcularHorasAPagar(int $minutosExtra, int $minutosMinimos): int
    {
        if ($minutosExtra < $minutosMinimos) {
            return 0;
        }

        return (int) (floor(($minutosExtra - $minutosMinimos) / $minutosMinimos) + 1);
    }

    private function parseTime(string $time): string
    {
        if (strlen($time) === 5) {
            return $time . ':00';
        }

        return $time;
    }
}
