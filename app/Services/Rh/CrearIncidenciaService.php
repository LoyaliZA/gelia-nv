<?php

namespace App\Services\Rh;

use App\Models\CatalogoTipoFalta;
use App\Models\RhColaborador;
use App\Models\RhIncidencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearIncidenciaService
{
    public function __construct(
        private CalcularPenalizacionIncidenciaService $calcularPenalizacion,
        private GenerarFolioIncidenciaService $generarFolio,
    ) {}

    public function ejecutar(User $registrador, array $datos): RhIncidencia
    {
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

        return DB::transaction(function () use ($registrador, $datos, $calculado, $fechaDeduccion) {
            $registro = RhIncidencia::create(array_merge([
                'uuid' => (string) Str::uuid(),
                'folio' => $this->generarFolio->ejecutar(),
                'fecha_ocurrencia' => $datos['fecha_ocurrencia'],
                'rh_colaborador_id' => $datos['rh_colaborador_id'],
                'catalogo_tipo_falta_id' => $datos['catalogo_tipo_falta_id'],
                'observaciones' => $datos['observaciones'] ?? null,
                'fecha_deduccion_nomina' => $fechaDeduccion,
                'registrado_por_id' => $registrador->id,
            ], $calculado));

            return $registro->load(['colaborador.departamento', 'colaborador.area', 'colaborador.puesto', 'tipoFalta', 'registradoPor']);
        });
    }
}
