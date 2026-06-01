<?php

namespace App\Services\Rh;

use App\Models\CatalogoTipoFalta;
use App\Models\RhColaborador;
use App\Models\RhIncidencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActualizarIncidenciaService
{
    public function __construct(
        private CalcularPenalizacionIncidenciaService $calcularPenalizacion,
    ) {}

    public function ejecutar(User $editor, RhIncidencia $registro, array $datos): RhIncidencia
    {
        if ($registro->estado_deduccion === 'aplicado') {
            throw ValidationException::withMessages([
                'estado_deduccion' => 'No se pueden editar incidencias ya aplicadas en nómina.',
            ]);
        }

        $colaborador = RhColaborador::findOrFail($datos['rh_colaborador_id']);
        $tipo = CatalogoTipoFalta::findOrFail($datos['catalogo_tipo_falta_id']);

        if (!$colaborador->activo) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'El colaborador debe estar activo.',
            ]);
        }

        if (!$tipo->activo) {
            throw ValidationException::withMessages([
                'catalogo_tipo_falta_id' => 'El tipo de incidencia debe estar activo.',
            ]);
        }

        $fechaDeduccion = $datos['fecha_deduccion_nomina'] ?? null;
        $calculado = $this->calcularPenalizacion->ejecutar($colaborador, $tipo, $fechaDeduccion);

        return DB::transaction(function () use ($registro, $datos, $calculado, $fechaDeduccion) {
            $registro->update(array_merge([
                'fecha_ocurrencia' => $datos['fecha_ocurrencia'],
                'rh_colaborador_id' => $datos['rh_colaborador_id'],
                'catalogo_tipo_falta_id' => $datos['catalogo_tipo_falta_id'],
                'observaciones' => $datos['observaciones'] ?? null,
                'fecha_deduccion_nomina' => $fechaDeduccion,
            ], $calculado));

            return $registro->fresh(['colaborador.departamento', 'colaborador.area', 'colaborador.puesto', 'tipoFalta', 'registradoPor']);
        });
    }
}
