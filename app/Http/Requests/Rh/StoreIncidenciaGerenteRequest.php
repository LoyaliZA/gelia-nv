<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidenciaGerenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.incidencias.gerente.crear')
            || $this->user()->can('rh.incidencias.crear')
            || $this->user()->can('rh.deducciones.crear');
    }

    public function rules(): array
    {
        return [
            'fecha_ocurrencia' => 'required|date',
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'catalogo_regla_incidencia_id' => 'required|exists:catalogo_reglas_incidencia,id',
            'producto_id' => 'nullable|exists:productos,id',
            'descripcion_detallada' => 'nullable|string|max:5000',
            'origen_deduccion' => 'nullable|in:nomina,comisiones',
            'fecha_deduccion_nomina' => 'nullable|date',
            'factor_multiplicador' => 'nullable|numeric|min:0.01',
        ];
    }
}
