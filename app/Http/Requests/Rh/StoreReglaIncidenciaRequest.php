<?php

namespace App\Http\Requests\Rh;

use App\Models\CatalogoReglaIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReglaIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.catalogos.incidencias_generales');
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'categoria' => ['required', Rule::in(array_keys(CatalogoReglaIncidencia::CATEGORIAS))],
            'tipo_comportamiento' => ['required', Rule::in(array_keys(CatalogoReglaIncidencia::COMPORTAMIENTOS))],
            'monto_fijo' => 'nullable|numeric|min:0|required_if:tipo_comportamiento,cobro_fijo',
            'catalogo_bono_id' => 'nullable|exists:catalogo_bonos,id|required_if:tipo_comportamiento,cancelacion_bono_especifico',
            'factor_penalizacion_puntualidad' => 'nullable|numeric|min:0',
            'factor_penalizacion_productividad' => 'nullable|numeric|min:0',
            'aplica_deduccion_salario_base' => 'nullable|boolean',
            'recompensa_auditor_activa' => 'nullable|boolean',
            'monto_recompensa_auditor' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
            'departamentos_aplicables' => 'nullable|array',
            'departamentos_aplicables.*' => 'exists:departamentos,id',
            'areas_aplicables' => 'nullable|array',
            'areas_aplicables.*' => 'exists:areas,id',
            'departamentos_visibilidad' => 'nullable|array',
            'departamentos_visibilidad.*' => 'exists:departamentos,id',
            'areas_visibilidad' => 'nullable|array',
            'areas_visibilidad.*' => 'exists:areas,id',
        ];
    }
}
