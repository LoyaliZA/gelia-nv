<?php

namespace App\Http\Requests\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\RhDeduccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeduccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.deducciones.crear') || $this->user()->can('rh.incidencias.crear');
    }

    public function rules(): array
    {
        return [
            'fecha_ocurrencia' => 'required|date',
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'catalogo_regla_incidencia_id' => 'required|exists:catalogo_reglas_incidencia,id',
            'producto_id' => 'nullable|exists:productos,id',
            'factor_multiplicador' => 'nullable|numeric|min:0.01',
            'descripcion_detallada' => 'nullable|string|max:5000',
            'origen_deduccion' => ['required', Rule::in([RhDeduccion::ORIGEN_NOMINA, RhDeduccion::ORIGEN_COMISIONES])],
            'fecha_deduccion_nomina' => 'nullable|date',
            'firma_gerente_data' => 'nullable|string',
            'firma_colaborador_data' => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $reglaId = $this->input('catalogo_regla_incidencia_id');
        if (!$reglaId) {
            return;
        }

        $regla = CatalogoReglaIncidencia::find($reglaId);
        if ($regla?->requiereProducto() && !$this->filled('producto_id')) {
            $this->merge(['producto_id' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $reglaId = $this->input('catalogo_regla_incidencia_id');
            if (!$reglaId) {
                return;
            }

            $regla = CatalogoReglaIncidencia::find($reglaId);
            if ($regla?->requiereProducto() && !$this->filled('producto_id')) {
                $validator->errors()->add('producto_id', 'Debe seleccionar un producto para este concepto.');
            }
        });
    }
}
