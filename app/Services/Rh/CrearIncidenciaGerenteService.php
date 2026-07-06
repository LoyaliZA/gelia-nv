<?php

namespace App\Services\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CrearIncidenciaGerenteService
{
    public function __construct(
        private FiltrarColaboradoresGerenteService $filtrarColaboradores,
        private CrearDeduccionService $crearDeduccion,
    ) {}

    public function ejecutar(User $gerente, array $datos): RhDeduccion
    {
        if (!$gerente->can('rh.incidencias.gerente.crear')
            && !$gerente->can('rh.incidencias.crear')
            && !$gerente->can('rh.deducciones.crear')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para registrar incidencias.',
            ]);
        }

        $colaborador = RhColaborador::findOrFail($datos['rh_colaborador_id']);
        $this->filtrarColaboradores->validarAcceso($gerente, $colaborador);

        $regla = CatalogoReglaIncidencia::findOrFail($datos['catalogo_regla_incidencia_id']);

        if (!$regla->disponiblePara($colaborador, $gerente)) {
            throw ValidationException::withMessages([
                'catalogo_regla_incidencia_id' => 'El concepto no está disponible para este colaborador.',
            ]);
        }

        $payload = array_merge($datos, [
            'origen_deduccion' => $datos['origen_deduccion'] ?? RhDeduccion::ORIGEN_NOMINA,
            'factor_multiplicador' => $datos['factor_multiplicador'] ?? 1,
        ]);

        unset($payload['firma_gerente_data'], $payload['firma_colaborador_data']);

        return $this->crearDeduccion->ejecutar($gerente, $payload);
    }
}
