<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarComisionesPlataformasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contabilidad.plataformas.configurar') ?? false;
    }

    public function rules(): array
    {
        return [
            'plataformas' => ['required', 'array', 'min:1'],
            'plataformas.*.id' => ['required', 'integer', 'exists:contabilidad_plataformas_pago,id'],
            'plataformas.*.tasa_comision_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
