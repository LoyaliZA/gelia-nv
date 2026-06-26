<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
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
     *   tarifa_hora_snapshot: float,
     *   multiplicador_snapshot: float,
     *   total_economico: float,
     *   monto_horas_extra: float,
     *   estado_pago: string,
     *   area_snapshot: ?string,
     * }
     */
    public function ejecutar(array $datos, ?RhConfiguracion $config = null, ?RhColaborador $colaborador = null): array
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
        $graciaMinutos = max(0, (int) ($config->he_gracia_minutos_despues_salida ?? 30));
        $minutosMinimos = max(1, (int) $config->he_minutos_minimos);

        $tiempoExtraMinutos = $this->calcularMinutosExtra(
            $entrada,
            $salida,
            $colaborador,
            $horasNormales,
            $graciaMinutos,
        );

        $tiempoExtraCrudo = round($tiempoExtraMinutos / 60, 2);
        $horasExtraAPagar = $this->calcularHorasAPagar($tiempoExtraMinutos, $minutosMinimos);

        $salarioPorHora = (float) ($datos['salario_por_hora_snapshot'] ?? 0);
        $multiplicador = (float) ($datos['multiplicador_snapshot'] ?? $config->he_multiplicador_pago ?? 2);
        $usarTarifaFija = array_key_exists('he_usar_tarifa_fija', $datos)
            ? (bool) $datos['he_usar_tarifa_fija']
            : (bool) ($config->he_usar_tarifa_fija ?? true);

        $tarifaHora = $usarTarifaFija
            ? (float) ($config->he_tarifa_hora_fija ?? 39)
            : $salarioPorHora;

        $montoHorasExtra = round($horasExtraAPagar * $multiplicador * $tarifaHora, 2);

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
            'tarifa_hora_snapshot' => $tarifaHora,
            'multiplicador_snapshot' => $multiplicador,
            'total_economico' => $montoHorasExtra,
            'monto_horas_extra' => $montoHorasExtra,
            'estado_pago' => $estadoPago,
            'area_snapshot' => $datos['area_snapshot'] ?? null,
        ];
    }

    private function calcularMinutosExtra(
        Carbon $entrada,
        Carbon $salida,
        ?RhColaborador $colaborador,
        float $horasNormales,
        int $graciaMinutos,
    ): int {
        $turno = $colaborador?->turno;
        if ($turno && !empty($turno->matriz_horario)) {
            // Mapping English day names to Spanish keys in JSON
            $mapaDias = [
                'Monday' => 'lunes',
                'Tuesday' => 'martes',
                'Wednesday' => 'miercoles',
                'Thursday' => 'jueves',
                'Friday' => 'viernes',
                'Saturday' => 'sabado',
                'Sunday' => 'domingo',
            ];
            
            $diaIngles = $entrada->copy()->format('l');
            $diaEspanol = $mapaDias[$diaIngles];
            $configDia = $turno->matriz_horario[$diaEspanol] ?? null;

            if ($configDia && !($configDia['descanso'] ?? false)) {
                $salidaOficial = $entrada->copy()->setTimeFromTimeString(
                    $this->parseTime($configDia['salida'])
                );
            } else {
                // If it's a rest day but they came in, we just take expected hours from $horasNormales or consider it all extra?
                // Usually on a rest day, all hours might be extra, but to keep backwards compatibility:
                $salidaOficial = $entrada->copy()->addMinutes((int) round($horasNormales * 60));
            }
        } elseif ($colaborador?->hora_salida_oficial) {
            $salidaOficial = $entrada->copy()->setTimeFromTimeString(
                $this->parseTime($colaborador->hora_salida_oficial),
            );
        } elseif ($colaborador?->hora_entrada_oficial) {
            $salidaOficial = $entrada->copy()->setTimeFromTimeString(
                $this->parseTime($colaborador->hora_entrada_oficial),
            )->addMinutes((int) round($horasNormales * 60));
        } else {
            $salidaOficial = $entrada->copy()->addMinutes((int) round($horasNormales * 60));
        }

        $inicioExtra = $salidaOficial->copy()->addMinutes($graciaMinutos);

        if ($salida->lte($inicioExtra)) {
            return 0;
        }

        return max(0, (int) $inicioExtra->diffInMinutes($salida));
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
