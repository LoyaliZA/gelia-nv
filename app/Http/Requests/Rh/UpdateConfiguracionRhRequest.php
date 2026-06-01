<?php

namespace App\Http\Requests\Rh;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfiguracionRhRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.configurar');
    }

    public function rules(): array
    {
        return [
            'folio_prefijo' => 'required|string|max:20',
            'folio_separador' => 'required|string|max:5',
            'folio_padding' => 'required|integer|min:1|max:12',
            'folio_incluir_anio' => 'nullable|boolean',
            'dias_periodo_pago' => 'required|integer|min:1|max:365',
            'decimales_salario_minuto' => 'required|integer|min:2|max:10',
            'recalcular_salarios' => 'nullable|boolean',
            'he_folio_prefijo' => 'required|string|max:20',
            'he_folio_padding' => 'required|integer|min:1|max:12',
            'he_multiplicador_pago' => 'required|numeric|min:1|max:10',
            'he_minutos_minimos' => 'required|integer|min:1|max:120',
            'inc_folio_prefijo' => 'required|string|max:20',
            'inc_folio_padding' => 'required|integer|min:1|max:12',
        ];
    }
}
