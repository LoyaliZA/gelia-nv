<?php

namespace App\Services\Rh;

use App\Models\RhSalidaPersonal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActualizarSalidaPersonalService
{
    public function __construct(
        private CalcularSalidaPersonalService $calcularSalida,
    ) {}

    public function ejecutar(User $usuario, RhSalidaPersonal $registro, array $datos): RhSalidaPersonal
    {
        if ($registro->fecha_deduccion_nomina !== null) {
            throw ValidationException::withMessages([
                'fecha_deduccion_nomina' => 'Esta salida ya fue cobrada en nómina y no se puede modificar.',
            ]);
        }

        // Subir nueva foto de salida si viene
        $rutaFotoSalida = $registro->evidencia_foto_salida;
        if (isset($datos['evidencia_foto_salida']) && $datos['evidencia_foto_salida'] instanceof UploadedFile) {
            $rutaFotoSalida = $datos['evidencia_foto_salida']->store('salidas_evidencias', 'public');
        }

        // Subir foto de regreso si viene
        $rutaFotoRegreso = $registro->evidencia_foto_regreso;
        if (isset($datos['evidencia_foto_regreso']) && $datos['evidencia_foto_regreso'] instanceof UploadedFile) {
            $rutaFotoRegreso = $datos['evidencia_foto_regreso']->store('salidas_evidencias', 'public');
        }

        // Si se define hora de regreso pero no hay foto de regreso (y no estaba previamente)
        if (!empty($datos['hora_regreso']) && empty($rutaFotoRegreso)) {
            throw ValidationException::withMessages([
                'evidencia_foto_regreso' => 'La evidencia fotográfica de regreso es obligatoria al registrar el retorno.',
            ]);
        }

        $datosCalculo = array_merge($datos, [
            'rh_colaborador_id' => $registro->rh_colaborador_id,
        ]);

        $calculado = $this->calcularSalida->ejecutar($datosCalculo, $registro->colaborador);

        return DB::transaction(function () use ($registro, $datos, $rutaFotoSalida, $rutaFotoRegreso, $calculado) {
            $registro->update([
                'fecha_evento' => $datos['fecha_evento'],
                'motivo' => $datos['motivo'],
                'hora_salida' => $datos['hora_salida'],
                'evidencia_foto_salida' => $rutaFotoSalida,
                'hora_regreso' => $datos['hora_regreso'] ?? null,
                'evidencia_foto_regreso' => $rutaFotoRegreso,
                'minutos_ausente' => $calculado['minutos_ausente'],
                'salario_por_minuto_snapshot' => $calculado['salario_por_minuto_snapshot'],
                'monto_a_deducir' => $calculado['monto_a_deducir'],
                'fecha_deduccion_nomina' => $datos['fecha_deduccion_nomina'] ?? null,
            ]);

            return $registro->fresh(['colaborador.area', 'colaborador.departamento', 'registradoPor']);
        });
    }
}
