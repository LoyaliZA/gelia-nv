<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhConfiguracion;
use App\Models\RhHorasExtra;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActualizarHorasExtraService
{
    public function __construct(
        private CalcularHorasExtraService $calcularHorasExtra,
    ) {}

    public function ejecutar(User $editor, RhHorasExtra $registro, array $datos): RhHorasExtra
    {
        if ($registro->estado_pago !== 'pendiente') {
            throw ValidationException::withMessages([
                'estado_pago' => 'Solo se pueden editar registros con pago pendiente.',
            ]);
        }

        $colaborador = RhColaborador::with('area')->findOrFail($datos['rh_colaborador_id']);

        if (!$colaborador->activo) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'El colaborador debe estar activo.',
            ]);
        }

        $config = RhConfiguracion::obtener();

        $datosCalculo = array_merge($datos, [
            'horas_normales_snapshot' => $colaborador->horas_laboradas_oficiales,
            'salario_por_hora_snapshot' => $colaborador->salario_por_hora,
            'multiplicador_snapshot' => $config->he_multiplicador_pago,
            'area_snapshot' => $colaborador->area?->nombre,
        ]);

        $calculado = $this->calcularHorasExtra->ejecutar($datosCalculo, $config, $colaborador);

        if ($calculado['total_horas_laboradas'] <= 0) {
            throw ValidationException::withMessages([
                'hora_salida' => 'El total de horas laboradas debe ser mayor a cero.',
            ]);
        }

        return DB::transaction(function () use ($registro, $datos, $calculado) {
            $registro->update(array_merge([
                'rh_colaborador_id' => $datos['rh_colaborador_id'],
                'fecha_turno' => $datos['fecha_turno'],
                'hora_entrada' => $datos['hora_entrada'],
                'hora_salida' => $datos['hora_salida'],
                'motivo' => $datos['motivo'],
                'supervisor_user_id' => $datos['supervisor_user_id'],
                'fecha_programada_pago' => $datos['fecha_programada_pago'] ?? null,
            ], $calculado));

            return $registro->fresh(['colaborador.area', 'colaborador.departamento', 'colaborador.puesto', 'supervisor', 'registradoPor']);
        });
    }
}
