<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhSalidaPersonal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearSalidaPersonalService
{
    public function __construct(
        private CalcularSalidaPersonalService $calcularSalida,
        private GenerarFolioSalidaService $generarFolio,
    ) {}

    public function ejecutar(User $registrador, array $datos): RhSalidaPersonal
    {
        $colaborador = RhColaborador::findOrFail($datos['rh_colaborador_id']);

        if (!$colaborador->activo) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'El colaborador debe estar activo.',
            ]);
        }

        // Subir foto de salida (obligatoria)
        $rutaFotoSalida = '';
        if (isset($datos['evidencia_foto_salida']) && $datos['evidencia_foto_salida'] instanceof UploadedFile) {
            $rutaFotoSalida = $datos['evidencia_foto_salida']->store('salidas_evidencias', 'public');
        } else {
            throw ValidationException::withMessages([
                'evidencia_foto_salida' => 'La evidencia fotográfica de salida es obligatoria.',
            ]);
        }

        $rutaFotoRegreso = null;
        if (isset($datos['evidencia_foto_regreso']) && $datos['evidencia_foto_regreso'] instanceof UploadedFile) {
            $rutaFotoRegreso = $datos['evidencia_foto_regreso']->store('salidas_evidencias', 'public');
        }

        // Si se registran salida y regreso de golpe
        $calculado = $this->calcularSalida->ejecutar($datos, $colaborador);

        return DB::transaction(function () use ($registrador, $datos, $colaborador, $rutaFotoSalida, $rutaFotoRegreso, $calculado) {
            $registro = RhSalidaPersonal::create([
                'uuid' => (string) Str::uuid(),
                'folio' => $this->generarFolio->ejecutar(),
                'rh_colaborador_id' => $colaborador->id,
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
                'registrado_por_id' => $registrador->id,
            ]);

            return $registro->load(['colaborador.area', 'colaborador.departamento', 'registradoPor']);
        });
    }
}
