<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.incidencias.editar');
    }

    public function rules(): array
    {
        return [
            'fecha_ocurrencia' => 'required|date',
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'catalogo_tipo_falta_id' => 'required|exists:catalogo_tipos_faltas,id',
            'observaciones' => 'nullable|string|max:2000',
            'fecha_deduccion_nomina' => 'nullable|date|after_or_equal:fecha_ocurrencia',
        ];
    }
}
